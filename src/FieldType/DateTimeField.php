<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\DateTimeLocal;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class DateTimeField extends AbstractFieldType
{
    public function key(): string
    {
        return 'datetime';
    }

    public function label(): string
    {
        return 'Date &amp; time';
    }

    public function icon(): string
    {
        return 'calendar-time';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'default', 'required', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new DateTimeLocal($field->name);
    }
}
