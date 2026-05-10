<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Select;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class SelectField extends AbstractFieldType
{
    public function key(): string
    {
        return 'select';
    }

    public function label(): string
    {
        return 'Drop-down (single)';
    }

    public function icon(): string
    {
        return 'select';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'required', 'choices', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        $element = new Select($field->name);
        $element->setValueOptions($this->extractChoices($field));
        return $element;
    }

    /** @return array<string, string> */
    private function extractChoices(FieldDefinition $field): array
    {
        $raw = $field->options['choices'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (is_array($entry) && isset($entry['value'])) {
                $out[(string) $entry['value']] = (string) ($entry['label'] ?? $entry['value']);
            }
        }
        return $out;
    }
}
