<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Time;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class TimeField extends AbstractFieldType
{
    public function key(): string
    {
        return 'time';
    }

    public function label(): string
    {
        return 'Time';
    }

    public function icon(): string
    {
        return 'clock';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'default', 'required', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Time($field->name);
    }
}
