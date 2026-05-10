<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Hidden;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class HiddenField extends AbstractFieldType
{
    public function key(): string
    {
        return 'hidden';
    }

    public function label(): string
    {
        return 'Hidden';
    }

    public function icon(): string
    {
        return 'eye-off';
    }

    public function supportedGroups(): array
    {
        return ['label', 'default', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Hidden($field->name);
    }
}
