<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Email;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class EmailField extends AbstractFieldType
{
    public function key(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return 'Email address';
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function supportedGroups(): array
    {
        return [
            'label', 'visibility', 'description', 'placeholder',
            'default', 'required', 'validation', 'conditional',
        ];
    }

    public function supportedValidators(): array
    {
        return ['confirm'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Email($field->name);
    }

    protected function applyHtml5Hints(ElementInterface $element, FieldDefinition $field): void
    {
        $element->setAttribute('autocomplete', 'email');
        $element->setAttribute('inputmode', 'email');
    }
}
