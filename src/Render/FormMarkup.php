<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Render;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\File;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\MultiCheckbox;
use Laminas\Form\Element\Radio;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Textarea;
use Laminas\Form\ElementInterface;
use Laminas\Form\FormInterface;
use Contenir\FormBuilder\Conditional\RuleEvaluator;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use Contenir\FormBuilder\Service\FormBuilderService;
use Contenir\FormBuilder\Html\FormContentSanitizer;

/**
 * Walks a {@see FormDefinition} and emits the form markup.
 *
 * Rendering is intentionally split from form construction: the
 * {@see \Contenir\FormBuilder\Service\FormBuilderService} produces a
 * validation-ready Laminas form, this helper produces the HTML. The layout
 * is derived from the definition (sections, groups, rows, col-spans), and
 * each input is rendered explicitly per element type — Laminas\Form has no
 * decorator system, so attribute and value emission lives here.
 */
/**
 * Walks a {@see FormDefinition} and emits the form markup.
 *
 * Framework-agnostic: holds no view-helper dependencies. Hosts that
 * want to plug in a framework's HTML escaper (Laminas-View's
 * `escapeHtml` plugin, Zend-View's `escape`, etc.) call
 * {@see setEscaper()} with a callable; otherwise the default
 * {@see htmlspecialchars} pass kicks in.
 *
 * The shipping adapter packages provide framework-specific view
 * helpers that wrap this renderer with framework-appropriate
 * escaping, so consuming sites typically call `$this->formMarkup(...)`
 * in templates instead of instantiating this class directly.
 */
class FormMarkup
{
    private RuleEvaluator $conditionalEvaluator;
    private bool $preview = false;

    /** @var (callable(string): string)|null */
    private $escaper = null;

    /** @var array<string, mixed> */
    private array $valueContext = [];

    public function __construct()
    {
        $this->conditionalEvaluator = new RuleEvaluator();
    }

    /**
     * Plug in a host-provided HTML escaper. Useful when the host
     * framework configures escape semantics (e.g. Laminas-View's
     * {@see \Laminas\View\Helper\EscapeHtml}). The callable takes a
     * string and returns the escaped string.
     *
     * @param callable(string): string $escaper
     */
    public function setEscaper(callable $escaper): void
    {
        $this->escaper = $escaper;
    }

    public function render(FormDefinition $definition, FormInterface $form, bool $preview = false): string
    {
        $this->preview      = $preview;
        $this->valueContext = $this->buildValueContext($form);

        if ($definition->layoutMode === FormDefinition::LAYOUT_STEPPED && $definition->sections !== []) {
            return $this->renderStepped($definition, $form);
        }

        $html  = $this->openForm($form);
        $html .= $this->renderSections($definition, $form);
        $html .= $this->renderActions($definition, $form);
        $html .= '</form>';

        return $html;
    }

    /**
     * Returns the BEM block prefix for structural wrappers (section /
     * group / row / field column). The preview path uses
     * `.form-preview__*` so it can have a self-contained stylesheet
     * without piggybacking on the admin's `.form__grid` layout system;
     * the public-facing path keeps the original `.form__*` classes.
     */
    private function classFor(string $element): string
    {
        return match ($element) {
            'section'             => $this->preview ? 'form-preview__section' : 'form__section',
            'section-title'       => $this->preview ? 'form-preview__section-title' : 'form__section-title',
            'section-description' => $this->preview ? 'form-preview__section-description' : 'form__section-description',
            'group'               => $this->preview ? 'form-preview__group' : 'form__panel',
            'group-description'   => $this->preview ? 'form-preview__group-description' : 'form__panel-description',
            'group-empty'         => $this->preview ? 'form-preview__group-empty' : 'form__panel-empty',
            'group-body'          => $this->preview ? 'form-preview__group-body' : 'form__group--wrapper',
            'row'                 => $this->preview ? 'form-preview__row' : 'form__grid',
            'field'               => $this->preview ? 'form-preview__field' : 'form__grid--col',
            default               => '',
        };
    }

    /** @return array<string, mixed> */
    private function buildValueContext(FormInterface $form): array
    {
        $context = [];
        foreach ($form->getElements() as $element) {
            $context[$element->getName()] = $element->getValue();
        }
        return $context;
    }

    private function openForm(FormInterface $form, bool $stepped = false): string
    {
        $attribs = $form->getAttributes();
        $defaults = [
            'method'       => 'post',
            'class'        => 'form form--stacked',
            'autocomplete' => 'on',
        ];
        foreach ($defaults as $key => $value) {
            if (! isset($attribs[$key]) || $attribs[$key] === '') {
                $attribs[$key] = $value;
            }
        }
        if ($stepped) {
            $attribs['class']             = trim((string) $attribs['class'] . ' form--stepped');
            $attribs['data-form-stepper'] = 'true';
        }
        if ($this->hasFileField($form)) {
            $attribs['enctype'] = 'multipart/form-data';
        }

        return '<form' . $this->htmlAttribs($attribs) . '>';
    }

    private function hasFileField(FormInterface $form): bool
    {
        foreach ($form->getElements() as $element) {
            if ($element instanceof File) {
                return true;
            }
        }
        return false;
    }

    private function renderSections(FormDefinition $definition, FormInterface $form): string
    {
        $html = '';
        foreach ($definition->sections as $section) {
            $html .= $this->renderSection($section, $form);
        }
        return $html;
    }

    private function renderSection(SectionDefinition $section, FormInterface $form): string
    {
        $body = $this->renderSectionBody($section, $form);
        if ($body === '') {
            return '';
        }

        $hasLegend      = $section->legend !== null && $section->legend !== '';
        $hasDescription = $section->description !== null && $section->description !== '';
        if (! $hasLegend && ! $hasDescription) {
            return $body;
        }

        $html = '<section class="' . $this->classFor('section') . '">';
        if ($hasLegend) {
            $html .= '<h2 class="' . $this->classFor('section-title') . '">'
                . $this->escape((string) $section->legend) . '</h2>';
        }
        if ($hasDescription) {
            $html .= '<p class="' . $this->classFor('section-description') . '">'
                . $this->escape((string) $section->description) . '</p>';
        }
        $html .= $body;
        $html .= '</section>';

        return $html;
    }

    private function renderSectionBody(SectionDefinition $section, FormInterface $form): string
    {
        $html = '';
        foreach ($section->groups as $group) {
            $html .= $this->renderGroup($group, $form);
        }
        return $html;
    }

    private function renderStepped(FormDefinition $definition, FormInterface $form): string
    {
        $sections = $definition->sections;
        $lastIndex = count($sections) - 1;

        $html  = $this->openForm($form, true);
        $html .= $this->renderStepNav($sections);

        foreach ($sections as $index => $section) {
            $isFirst  = $index === 0;
            $isLast   = $index === $lastIndex;
            $stepKey  = $this->escape($section->key);
            $hidden   = $isFirst ? '' : ' hidden';
            $active   = $isFirst ? ' is-active' : '';

            $html .= sprintf(
                '<section class="form__step%s" data-form-step="%s"%s>',
                $active,
                $stepKey,
                $hidden,
            );

            if ($section->legend !== null && $section->legend !== '') {
                $html .= '<h2 class="form__step-title">' . $this->escape($section->legend) . '</h2>';
            }
            if ($section->description !== null && $section->description !== '') {
                $html .= '<p class="form__step-description">' . $this->escape($section->description) . '</p>';
            }

            $html .= $this->renderSectionBody($section, $form);
            $html .= $this->renderStepNavButtons($definition, $form, $isFirst, $isLast);
            $html .= '</section>';
        }

        $html .= '</form>';
        return $html;
    }

    /** @param list<SectionDefinition> $sections */
    private function renderStepNav(array $sections): string
    {
        $html = '<ol class="form__steps" role="tablist">';
        foreach ($sections as $index => $section) {
            $isFirst  = $index === 0;
            $disabled = $isFirst ? '' : ' disabled';
            $active   = $isFirst ? ' is-active' : '';
            $label    = $section->legend !== null && $section->legend !== ''
                ? $section->legend
                : ucfirst(str_replace(['-', '_'], ' ', $section->key));

            $html .= sprintf(
                '<li><button type="button" class="form__step-tab%s" data-form-step-target="%s"%s>'
                    . '<span class="form__step-tab-index">%d</span> %s</button></li>',
                $active,
                $this->escape($section->key),
                $disabled,
                $index + 1,
                $this->escape($label),
            );
        }
        $html .= '</ol>';
        return $html;
    }

    private function renderStepNavButtons(
        FormDefinition $definition,
        FormInterface $form,
        bool $isFirst,
        bool $isLast,
    ): string {
        $html = '<nav class="form__step-nav">';
        if (! $isFirst) {
            $html .= '<button type="button" class="btn" data-form-step-prev>Previous</button>';
        }
        if ($isLast) {
            $html .= $this->renderActions($definition, $form);
        } else {
            $html .= '<button type="button" class="btn btn--primary" data-form-step-next>Next</button>';
        }
        $html .= '</nav>';
        return $html;
    }

    private function renderGroup(GroupDefinition $group, FormInterface $form): string
    {
        $rows = '';
        foreach ($group->rows as $row) {
            $rows .= $this->renderRow($row, $form);
        }

        $html = '<fieldset class="' . $this->classFor('group') . '">';
        if ($group->legend !== null && $group->legend !== '') {
            $html .= '<legend>' . $this->escape($group->legend) . '</legend>';
        }
        if ($group->description !== null && $group->description !== '') {
            $html .= '<p class="' . $this->classFor('group-description') . '">'
                . $this->escape($group->description) . '</p>';
        }
        if ($rows === '') {
            $html .= '<p class="' . $this->classFor('group-empty') . '"><em>No fields in this group yet.</em></p>';
        } else {
            $html .= '<div class="' . $this->classFor('group-body') . '">' . $rows . '</div>';
        }
        $html .= '</fieldset>';

        return $html;
    }

    private function renderRow(RowDefinition $row, FormInterface $form): string
    {
        if ($row->fields === []) {
            return '';
        }

        $cols = '';
        foreach ($row->fields as $field) {
            if ($field->type === 'content') {
                $cols .= $this->renderContent($field);
                continue;
            }
            if (! $form->has($field->name)) {
                continue;
            }
            $element = $form->get($field->name);
            $cols   .= $this->renderField($field, $element, $form);
        }
        if ($cols === '') {
            return '';
        }

        return '<div class="' . $this->classFor('row') . '">' . $cols . '</div>';
    }

    /**
     * Render a static content block. The HTML body lives on
     * `field->options['html']` and runs through FormContentSanitizer
     * here so the output is always safe to dump verbatim into the
     * page (script / iframe / form / event-handler attributes are
     * stripped, disallowed wrappers are unwrapped).
     */
    private function renderContent(FieldDefinition $field): string
    {
        $raw = (string) ($field->options['html'] ?? '');
        if (trim($raw) === '') {
            return '';
        }

        $colAttribs = ['class' => $this->classFor('field')];

        if ($field->conditional !== null && $field->conditional !== []) {
            $encoded = json_encode($field->conditional, JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $colAttribs['data-form-conditional'] = $encoded;
                if (! $this->conditionalEvaluator->shouldShow($field->conditional, $this->valueContext)) {
                    $colAttribs['hidden'] = 'hidden';
                }
            }
        }

        $body = FormContentSanitizer::sanitize($raw);
        $contentClass = $this->preview ? 'form-preview__content' : 'form__content';

        return '<div' . $this->htmlAttribs($colAttribs) . '>'
            . '<div class="' . $contentClass . '">' . $body . '</div>'
            . '</div>';
    }

    private function renderField(FieldDefinition $field, ElementInterface $element, FormInterface $form): string
    {
        $colAttribs = [
            'class' => $this->classFor('field'),
        ];

        if ($field->conditional !== null && $field->conditional !== []) {
            $encoded = json_encode($field->conditional, JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $colAttribs['data-form-conditional'] = $encoded;
                if (! $this->conditionalEvaluator->shouldShow($field->conditional, $this->valueContext)) {
                    $colAttribs['hidden'] = 'hidden';
                }
            }
        }

        $html = '<div' . $this->htmlAttribs($colAttribs) . '>';

        // Checkboxes draw their own label inline (the styled custom-checkbox
        // UI uses an `<input type="checkbox" class="form__control--checkbox">`
        // followed by a `<label for="…">`, so the label sits AFTER the input
        // rather than before). The renderCheckbox helper emits both.
        $isCheckbox = $element instanceof Checkbox;

        if (! $isCheckbox && $field->showLabel && $field->label !== null && $field->label !== '') {
            $required = $field->required ? ' <abbr class="form__required" title="Required">*</abbr>' : '';
            $html    .= sprintf(
                '<label for="%s">%s%s</label>',
                $this->escape($field->name),
                $this->escape($field->label),
                $required,
            );
        }

        $html .= $this->renderInput($element);

        if ($field->description !== null && $field->description !== '') {
            $html .= '<p class="form__description">' . $this->escape($field->description) . '</p>';
        }

        $html .= $this->renderErrors($form, $field->name);
        $html .= '</div>';

        return $html;
    }

    private function renderActions(FormDefinition $definition, FormInterface $form): string
    {
        $alignment = $this->escape($definition->submitAlignment);
        $html      = '<div class="form__actions form__actions--' . $alignment . '">';

        if ($form->has(FormBuilderService::CSRF_NAME)) {
            $html .= $this->renderInput($form->get(FormBuilderService::CSRF_NAME));
        }

        if ($form->has(FormBuilderService::HONEYPOT_NAME)) {
            $html .= '<div class="form__hid-wrapper" aria-hidden="true">'
                . $this->renderInput($form->get(FormBuilderService::HONEYPOT_NAME))
                . '</div>';
        }

        if ($form->has('_submit')) {
            $html .= $this->renderInput($form->get('_submit'));
        }

        $html .= '</div>';

        return $html;
    }

    private function renderInput(ElementInterface $element): string
    {
        return match (true) {
            $element instanceof Textarea      => $this->renderTextarea($element),
            $element instanceof Radio         => $this->renderChoiceList($element, 'radio'),
            $element instanceof MultiCheckbox => $this->renderChoiceList($element, 'checkbox'),
            $element instanceof Checkbox      => $this->renderCheckbox($element),
            $element instanceof Select        => $this->renderSelect($element),
            $element instanceof Submit        => $this->renderSubmit($element),
            default                           => $this->renderInputTag($element),
        };
    }

    private function renderInputTag(ElementInterface $element): string
    {
        $attribs = $element->getAttributes();
        $attribs['name'] = $element->getName();
        if (! isset($attribs['type'])) {
            $attribs['type'] = 'text';
        }
        if (! isset($attribs['id']) || $attribs['id'] === '') {
            $attribs['id'] = $element->getName();
        }
        $value = $element->getValue();
        if ($value !== null && $value !== '') {
            $attribs['value'] = is_scalar($value) ? (string) $value : '';
        }
        return '<input' . $this->htmlAttribs($attribs) . '>';
    }

    private function renderSubmit(Submit $element): string
    {
        $attribs = $element->getAttributes();
        $attribs['type'] = 'submit';
        $attribs['name'] = $element->getName();
        $value = $element->getValue();
        if (! is_scalar($value) || (string) $value === '') {
            $value = $element->getLabel() ?? '';
        }
        $attribs['value'] = (string) $value;
        return '<input' . $this->htmlAttribs($attribs) . '>';
    }

    private function renderTextarea(Textarea $element): string
    {
        $attribs = $element->getAttributes();
        $attribs['name'] = $element->getName();
        if (! isset($attribs['id']) || $attribs['id'] === '') {
            $attribs['id'] = $element->getName();
        }
        unset($attribs['type']);
        $value = $element->getValue();
        $body  = is_scalar($value) ? $this->escape((string) $value) : '';
        return '<textarea' . $this->htmlAttribs($attribs) . '>' . $body . '</textarea>';
    }

    private function renderSelect(Select $element): string
    {
        $attribs = $element->getAttributes();
        $name    = $element->getName();
        $isMulti = ! empty($attribs['multiple']);
        $attribs['name'] = $isMulti ? $name . '[]' : $name;
        if (! isset($attribs['id']) || $attribs['id'] === '') {
            $attribs['id'] = $name;
        }
        unset($attribs['type']);

        // Single-value selects pick up `form__control--select` so the
        // project's chevron + appearance reset apply. Multi-selects keep
        // their native list rendering (no chevron makes sense).
        if (! $isMulti) {
            $class = (string) ($attribs['class'] ?? '');
            if (strpos($class, 'form__control--select') === false) {
                $attribs['class'] = trim($class . ' form__control--select');
            }
        }

        $selected = $this->normaliseSelected($element->getValue());

        $options = '';
        foreach ($element->getValueOptions() as $value => $label) {
            $optAttribs = ['value' => (string) $value];
            if (in_array((string) $value, $selected, true)) {
                $optAttribs['selected'] = 'selected';
            }
            $options .= '<option' . $this->htmlAttribs($optAttribs) . '>'
                . $this->escape((string) $label) . '</option>';
        }

        return '<select' . $this->htmlAttribs($attribs) . '>' . $options . '</select>';
    }

    private function renderChoiceList(MultiCheckbox $element, string $type): string
    {
        $name     = $element->getName();
        $isRadio  = $type === 'radio';
        $selected = $this->normaliseSelected($element->getValue());

        $html = '<div class="form__control--checkbox-list">';
        foreach ($element->getValueOptions() as $value => $label) {
            $inputId = sprintf('%s-%s', $name, preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $value));
            $attribs = [
                'type'  => $type,
                'name'  => $isRadio ? $name : $name . '[]',
                'id'    => $inputId,
                'value' => (string) $value,
                'class' => 'form__control--checkbox',
            ];
            if (in_array((string) $value, $selected, true)) {
                $attribs['checked'] = 'checked';
            }
            $html .= '<span class="form__control--checkbox-list-item">';
            $html .= '<input' . $this->htmlAttribs($attribs) . '>';
            $html .= '<label for="' . $this->escape($inputId) . '">' . $this->escape((string) $label) . '</label>';
            $html .= '</span>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderCheckbox(Checkbox $element): string
    {
        $attribs = $element->getAttributes();
        $attribs['type'] = 'checkbox';
        $attribs['name'] = $element->getName();
        if (! isset($attribs['id']) || $attribs['id'] === '') {
            $attribs['id'] = $element->getName();
        }

        // Replace the generic `form__control` class with `form__control--checkbox`
        // so the project's hidden-input + sibling-label custom-checkbox UI
        // applies (the AbstractFieldType always sets `form__control` because
        // most field types want it; only the checkbox swap happens here).
        $class = (string) ($attribs['class'] ?? '');
        $class = trim((string) preg_replace('/\bform__control\b/', 'form__control--checkbox', $class));
        if (strpos($class, 'form__control--checkbox') === false) {
            $class = trim('form__control--checkbox ' . $class);
        }
        $attribs['class'] = $class;

        $checkedValue   = $element->getCheckedValue();
        $uncheckedValue = $element->getUncheckedValue();
        $current        = $element->getValue();

        $attribs['value'] = (string) $checkedValue;
        if ((string) $current === (string) $checkedValue) {
            $attribs['checked'] = 'checked';
        }

        $hidden = '';
        if ($uncheckedValue !== '') {
            $hidden = sprintf(
                '<input type="hidden" name="%s" value="%s">',
                $this->escape($element->getName()),
                $this->escape((string) $uncheckedValue),
            );
        }

        $label     = (string) ($element->getLabel() ?? '');
        $labelHtml = $label !== ''
            ? sprintf('<label for="%s">%s</label>', $this->escape((string) $attribs['id']), $this->escape($label))
            : '';

        return $hidden . '<input' . $this->htmlAttribs($attribs) . '>' . $labelHtml;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normaliseSelected(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(static fn ($v): string => (string) $v, $value);
        }
        if ($value === null || $value === '') {
            return [];
        }
        return [is_scalar($value) ? (string) $value : ''];
    }

    private function renderErrors(FormInterface $form, string $name): string
    {
        $messages = $form->getMessages($name);
        if (! is_array($messages) || $messages === []) {
            return '';
        }

        $items = '';
        foreach ($messages as $message) {
            if (is_array($message)) {
                foreach ($message as $nested) {
                    $items .= '<li>' . $this->escape((string) $nested) . '</li>';
                }
                continue;
            }
            $items .= '<li>' . $this->escape((string) $message) . '</li>';
        }

        return '<ul class="form__errors">' . $items . '</ul>';
    }

    private function escape(string $value): string
    {
        if ($this->escaper !== null) {
            return ($this->escaper)($value);
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, scalar|null> $attribs */
    private function htmlAttribs(array $attribs): string
    {
        $out = '';
        foreach ($attribs as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $out .= sprintf(' %s="%s"', $name, $this->escape((string) $value));
        }
        return $out;
    }
}
