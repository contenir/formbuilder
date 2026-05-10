<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Service;

use Contenir\FormBuilder\Conditional\RuleEvaluator;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\Storage\StorageManager;
use Laminas\Form\FormInterface;

/**
 * Coordinates server-side form submission: build, validate, dispatch.
 *
 * The service owns the spam detection rule (a non-empty honeypot) and the
 * SplSubject/Observer wiring so registrars don't need to know how to inspect
 * the Laminas form internals. Returns a {@see SubmissionResult} the caller
 * uses to decide on success/failure rendering.
 *
 * Optional `$storageManager` is consulted for `file` field uploads. When
 * null (or unconfigured for the default profile), file uploads are
 * silently skipped — this keeps the engine usable in tests and in
 * deployments that don't accept uploads.
 */
class FormSubmissionService
{
    /** @var list<\SplObserver> */
    private array $observers = [];

    private RuleEvaluator $conditionalEvaluator;

    public function __construct(
        private FormBuilderService $builder,
        private ?StorageManager $storageManager = null,
    ) {
        $this->conditionalEvaluator = new RuleEvaluator();
    }

    public function attach(\SplObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files    Shape of `$_FILES` — keyed by field name,
     *                                       each value `{name, type, tmp_name, error, size}`.
     * @param array<string, mixed> $context  ip, user_id, meta
     */
    public function submit(
        FormDefinition $form,
        array $post,
        array $files = [],
        array $context = [],
    ): SubmissionResult {
        $built = $this->builder->build($form);

        $isSpam = $this->detectSpam($post);
        if ($isSpam) {
            unset($post[FormBuilderService::HONEYPOT_NAME]);
        }

        $post = $this->processFileUploads($form, $post, $files);

        $hiddenFields = $this->applyConditionalGating($built, $form, $post);

        $built->setData($post);
        $valid = $built->isValid();

        if (! $valid && ! $isSpam) {
            return new SubmissionResult(
                valid: false,
                form: $built,
                values: [],
                errors: $built->getMessages(),
                isSpam: false,
            );
        }

        $values = $this->collectValues($built, $post, $valid);
        foreach ($hiddenFields as $name) {
            unset($values[$name]);
        }

        $registry = [
            'form'    => $form,
            'values'  => $values,
            'spam'    => $isSpam,
            'context' => $context,
        ];
        if ($built instanceof BuilderForm) {
            $built->registry = new \ArrayObject($registry, \ArrayObject::ARRAY_AS_PROPS);
        }

        foreach ($this->observers as $observer) {
            $observer->update($built);
        }

        $entryAttrs = $this->buildEntryAttributes($built, $context);
        if ($built instanceof BuilderForm && $built->registry instanceof \ArrayObject) {
            $built->registry['entry'] = $entryAttrs;
        }

        return new SubmissionResult(
            valid: ! $isSpam,
            form: $built,
            values: $values,
            errors: [],
            isSpam: $isSpam,
            entryId: $this->extractEntryId($built),
        );
    }

    /** @param array<string, mixed> $post */
    private function detectSpam(array $post): bool
    {
        $honeypot = $post[FormBuilderService::HONEYPOT_NAME] ?? '';
        return is_string($honeypot) ? trim($honeypot) !== '' : true;
    }

    /**
     * Stash any uploaded files via the project's Storage layer before
     * validation runs, replacing the file-field's value in $post with
     * the relative storage path. After this step the form treats file
     * fields like any other scalar — validation, conditional gating,
     * persistence and notifications all see a string path rather than
     * an upload struct, so none of them need a special case.
     *
     * Files land under `forms/<form-slug>/` in the configured Storage
     * profile; the storage layer disambiguates filenames so multiple
     * submissions to the same form can't overwrite each other.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private function processFileUploads(FormDefinition $form, array $post, array $files): array
    {
        if ($files === []) {
            return $post;
        }
        foreach ($form->getAllFields() as $field) {
            if ($field->type !== 'file') {
                continue;
            }
            $upload = $files[$field->name] ?? null;
            if (! is_array($upload)) {
                continue;
            }
            $error = (int) ($upload['error'] ?? \UPLOAD_ERR_NO_FILE);
            if ($error !== \UPLOAD_ERR_OK) {
                continue;
            }
            $tmpPath = (string) ($upload['tmp_name'] ?? '');
            if ($tmpPath === '' || ! is_uploaded_file($tmpPath)) {
                continue;
            }

            $stored = $this->storeUpload(
                $form,
                $tmpPath,
                (string) ($upload['name'] ?? 'upload'),
                (string) ($upload['type'] ?? ''),
            );
            if ($stored !== null) {
                $post[$field->name] = $stored;
            }
        }
        return $post;
    }

    private function storeUpload(FormDefinition $form, string $tmpPath, string $clientName, string $mimeType): ?string
    {
        if ($this->storageManager === null) {
            return null;
        }
        if (! $this->storageManager->has(StorageManager::DEFAULT_PROFILE)) {
            return null;
        }
        $backend = $this->storageManager->get(StorageManager::DEFAULT_PROFILE);

        $entry = $backend->store(
            new \Contenir\Storage\UploadInput(
                $tmpPath,
                $clientName,
                $mimeType !== '' ? $mimeType : null,
            ),
            'forms/' . trim($form->slug, '/'),
        );

        return $entry->path;
    }

    /**
     * Walks every field with a conditional rule and, for those whose rule fails
     * against the submitted data, relaxes the corresponding InputFilter input
     * (non-required + allow-empty) and excludes it from the validation group.
     * Returns the names of hidden fields so the caller can drop their values
     * from the submission payload — leftover state in $post for a hidden field
     * must not be persisted.
     *
     * @param array<string, mixed> $post
     * @return list<string>
     */
    private function applyConditionalGating(FormInterface $built, FormDefinition $form, array $post): array
    {
        $inputFilter = $built->getInputFilter();
        $hidden      = [];

        foreach ($form->getAllFields() as $field) {
            if ($field->conditional === null || ! $inputFilter->has($field->name)) {
                continue;
            }
            if ($this->conditionalEvaluator->shouldShow($field->conditional, $post)) {
                continue;
            }
            $hidden[]  = $field->name;
            $input     = $inputFilter->get($field->name);
            $input->setRequired(false);
            $input->setAllowEmpty(true);
        }

        if ($hidden !== []) {
            $allNames = array_keys($inputFilter->getInputs());
            $built->setValidationGroup(array_values(array_diff($allNames, $hidden)));
        }

        return $hidden;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function collectValues(FormInterface $form, array $post, bool $valid): array
    {
        if ($valid) {
            $data = $form->getData(FormInterface::VALUES_NORMALIZED);
            $values = is_array($data) ? $data : $post;
        } else {
            $values = $post;
        }
        unset(
            $values[FormBuilderService::CSRF_NAME],
            $values[FormBuilderService::HONEYPOT_NAME],
            $values['_submit'],
        );
        return $values;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildEntryAttributes(FormInterface $form, array $context): array
    {
        return [
            'id'     => $this->extractEntryId($form),
            'date'   => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'ip'     => $context['ip'] ?? '',
            'status' => ($form instanceof BuilderForm && $form->registry instanceof \ArrayObject)
                ? $form->registry['entry_status'] ?? 'complete'
                : 'complete',
        ];
    }

    private function extractEntryId(FormInterface $form): ?int
    {
        if (! $form instanceof BuilderForm || ! $form->registry instanceof \ArrayObject) {
            return null;
        }
        $entryId = $form->registry['entry_id'] ?? null;
        return is_int($entryId) ? $entryId : null;
    }
}
