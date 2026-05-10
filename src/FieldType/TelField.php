<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Tel;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class TelField extends AbstractFieldType
{
    public function key(): string
    {
        return 'tel';
    }

    public function label(): string
    {
        return 'Telephone';
    }

    public function icon(): string
    {
        return 'phone';
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Tel($field->name);
    }

    protected function applyHtml5Hints(ElementInterface $element, FieldDefinition $field): void
    {
        $element->setAttribute('inputmode', 'tel');
        $pattern = $field->options['pattern'] ?? null;
        if (! is_string($pattern) || $pattern === '') {
            foreach ($field->validators as $validator) {
                if ($validator->type === 'regex' && isset($validator->options['pattern'])) {
                    $pattern = (string) $validator->options['pattern'];
                    break;
                }
            }
        }
        if (is_string($pattern) && $pattern !== '') {
            $element->setAttribute('pattern', $pattern);
        }
        $maxLength = $field->options['max_length'] ?? null;
        if (is_int($maxLength) || (is_string($maxLength) && $maxLength !== '')) {
            $element->setAttribute('maxlength', (string) $maxLength);
        }
    }
}
