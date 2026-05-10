<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Date;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class DateField extends AbstractFieldType
{
    public function key(): string
    {
        return 'date';
    }

    public function label(): string
    {
        return 'Date';
    }

    public function icon(): string
    {
        return 'calendar';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'default', 'required', 'options', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Date($field->name);
    }

    protected function applyHtml5Hints(ElementInterface $element, FieldDefinition $field): void
    {
        foreach (['min', 'max'] as $attr) {
            $value = $field->options[$attr] ?? null;
            if (is_string($value) && $value !== '') {
                $element->setAttribute($attr, $value);
            }
        }
    }
}
