<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\File;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class FileField extends AbstractFieldType
{
    public function key(): string
    {
        return 'file';
    }

    public function label(): string
    {
        return 'File upload';
    }

    public function icon(): string
    {
        return 'paperclip';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'required', 'options', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new File($field->name);
    }

    protected function applyHtml5Hints(ElementInterface $element, FieldDefinition $field): void
    {
        $accept = $field->options['accept'] ?? null;
        if (is_string($accept) && $accept !== '') {
            $element->setAttribute('accept', $accept);
        }
    }
}
