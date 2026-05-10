<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Service;

use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Text;
use Laminas\Form\FormInterface;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Identical;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\ValidatorDefinition;
use Contenir\FormBuilder\FieldType\FieldTypeRegistry;
use Contenir\FormBuilder\Validator\ValidatorFactory;

/**
 * Assembles a Laminas form instance from a {@see FormDefinition}.
 *
 * The produced form always carries:
 *  - a CSRF element ({@see CSRF_NAME}) that token-checks the submission,
 *  - a honeypot text element ({@see HONEYPOT_NAME}) used by the submission
 *    service to flag bot traffic without rejecting humans,
 *  - a submit button governed by the form's submit_label,
 *
 * plus user-defined fields configured with the curated validators and filters
 * declared on each {@see FieldDefinition}. Validation is configured on the
 * form's input filter; rendering is the host's
 * helper's responsibility.
 */
class FormBuilderService
{
    public const CSRF_NAME     = '_csrf';
    public const HONEYPOT_NAME = 'hid';

    public function __construct(
        private FieldTypeRegistry $registry,
        private ValidatorFactory $validatorFactory,
    ) {
    }

    public function build(FormDefinition $form): FormInterface
    {
        $builder = new BuilderForm();
        $builder->setAttribute('method', 'post');
        $builder->setAttribute('class', 'form form--stacked');
        $builder->setAttribute('autocomplete', 'on');

        $inputFilter  = new InputFilter();
        $confirmPairs = [];

        foreach ($form->sections as $section) {
            foreach ($section->groups as $group) {
                foreach ($group->rows as $row) {
                    foreach ($row->fields as $field) {
                        if (! $this->registry->has($field->type)) {
                            continue;
                        }
                        $type = $this->registry->get($field->type);
                        if ($type->isStatic()) {
                            // Static blocks (content / instructions) don't
                            // produce inputs — FormMarkup renders their
                            // body separately at the row's column.
                            continue;
                        }
                        $element = $type->buildElement($field);
                        $builder->add($element);
                        $inputFilter->add($this->buildInput($field, $confirmPairs));
                    }
                }
            }
        }

        foreach ($confirmPairs as [$source, $target, $message]) {
            if (! $inputFilter->has($source)) {
                continue;
            }
            $identical = new Identical(['token' => $target]);
            if ($message !== null && $message !== '') {
                $identical->setMessage($message);
            }
            $inputFilter->get($source)->getValidatorChain()->attach($identical);
        }

        $csrfElement = $this->buildCsrfElement();
        $builder->add($csrfElement);
        $inputFilter->add($this->buildCsrfInput($csrfElement));

        $builder->add($this->buildHoneypotElement());
        $inputFilter->add($this->buildHoneypotInput());

        $builder->add($this->buildSubmitElement($form));
        $inputFilter->add($this->buildSubmitInput());

        $builder->setInputFilter($inputFilter);

        return $builder;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string|null}> $confirmPairs
     */
    private function buildInput(FieldDefinition $field, array &$confirmPairs): Input
    {
        $input = new Input($field->name);
        $input->setRequired($field->required);
        $input->setAllowEmpty(! $field->required);

        foreach ($field->validators as $validator) {
            if (! $validator instanceof ValidatorDefinition) {
                continue;
            }

            if ($validator->type === ValidatorFactory::TYPE_REQUIRED) {
                $input->setRequired(true);
                $input->setAllowEmpty(false);
                continue;
            }

            if ($validator->type === ValidatorFactory::TYPE_CONFIRM) {
                $target = (string) ($validator->options['field'] ?? '');
                if ($target !== '') {
                    $confirmPairs[] = [$field->name, $target, $validator->message];
                }
                continue;
            }

            $instance = $this->validatorFactory->create($validator);
            if ($instance !== null) {
                $input->getValidatorChain()->attach($instance);
            }
        }

        foreach ($field->filters as $filter) {
            $input->getFilterChain()->attachByName($filter);
        }

        return $input;
    }

    private function buildCsrfElement(): Csrf
    {
        return new Csrf(self::CSRF_NAME, [
            'csrf_options' => [
                'salt' => 'contenir_formbuilder',
            ],
        ]);
    }

    private function buildCsrfInput(Csrf $element): Input
    {
        $input = new Input(self::CSRF_NAME);
        $input->setRequired(true);
        $input->getValidatorChain()->attach($element->getCsrfValidator());
        return $input;
    }

    private function buildHoneypotElement(): Text
    {
        $element = new Text(self::HONEYPOT_NAME);
        $element->setLabel('');
        $element->setAttribute('autocomplete', 'off');
        $element->setAttribute('tabindex', '-1');
        $element->setAttribute('aria-hidden', 'true');
        $element->setAttribute('class', 'form__hid');
        return $element;
    }

    private function buildHoneypotInput(): Input
    {
        $input = new Input(self::HONEYPOT_NAME);
        $input->setRequired(false);
        $input->setAllowEmpty(true);
        return $input;
    }

    private function buildSubmitElement(FormDefinition $form): Submit
    {
        $element = new Submit('_submit');
        $element->setLabel($form->submitLabel);
        $element->setValue($form->submitLabel);
        $element->setAttribute('class', 'btn btn--primary');
        return $element;
    }

    private function buildSubmitInput(): Input
    {
        $input = new Input('_submit');
        $input->setRequired(false);
        $input->setAllowEmpty(true);
        return $input;
    }
}
