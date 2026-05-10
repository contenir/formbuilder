<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Url;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class UrlField extends AbstractFieldType
{
    public function key(): string
    {
        return 'url';
    }

    public function label(): string
    {
        return 'URL';
    }

    public function icon(): string
    {
        return 'link';
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
        return new Url($field->name);
    }

    protected function applyHtml5Hints(ElementInterface $element, FieldDefinition $field): void
    {
        $element->setAttribute('inputmode', 'url');
    }
}
