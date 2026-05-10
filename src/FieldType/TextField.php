<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Text;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class TextField extends AbstractFieldType
{
    public function key(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Single-line text';
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Text($field->name);
    }

    protected function applyHtml5Hints(ElementInterface $element, FieldDefinition $field): void
    {
        $pattern   = $field->options['pattern'] ?? null;
        $maxLength = $field->options['max_length'] ?? null;
        foreach ($field->validators as $validator) {
            if (
                (! is_string($pattern) || $pattern === '')
                && $validator->type === 'regex'
                && isset($validator->options['pattern'])
            ) {
                $pattern = (string) $validator->options['pattern'];
            }
            if (
                ! is_numeric($maxLength)
                && $validator->type === 'string_length'
                && isset($validator->options['max'])
            ) {
                $maxLength = (string) $validator->options['max'];
            }
        }
        if (is_string($pattern) && $pattern !== '') {
            $element->setAttribute('pattern', $pattern);
        }
        if (is_int($maxLength) || (is_string($maxLength) && $maxLength !== '')) {
            $element->setAttribute('maxlength', (string) $maxLength);
        }
    }
}
