<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Textarea;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class TextareaField extends AbstractFieldType
{
    public function key(): string
    {
        return 'textarea';
    }

    public function label(): string
    {
        return 'Multi-line text';
    }

    public function icon(): string
    {
        return 'list';
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        $element = new Textarea($field->name);
        $rows    = $field->options['rows'] ?? 5;
        $element->setAttribute('rows', (string) (int) $rows);
        return $element;
    }
}
