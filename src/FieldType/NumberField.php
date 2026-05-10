<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Number;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class NumberField extends AbstractFieldType
{
    public function key(): string
    {
        return 'number';
    }

    public function label(): string
    {
        return 'Number';
    }

    public function icon(): string
    {
        return 'hash';
    }

    public function supportedValidators(): array
    {
        return ['between', 'confirm'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Number($field->name);
    }

    protected function applyHtml5Hints(ElementInterface $element, FieldDefinition $field): void
    {
        foreach (['min', 'max', 'step'] as $attr) {
            $value = $field->options[$attr] ?? null;
            if (is_numeric($value)) {
                $element->setAttribute($attr, (string) $value);
            }
        }
        $element->setAttribute('inputmode', 'numeric');
    }
}
