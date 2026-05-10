<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class CheckboxField extends AbstractFieldType
{
    public function key(): string
    {
        return 'checkbox';
    }

    public function label(): string
    {
        return 'Checkbox (single)';
    }

    public function icon(): string
    {
        return 'square-check';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'default', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Checkbox($field->name);
    }
}
