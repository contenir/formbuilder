<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Select;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class MultiselectField extends AbstractFieldType
{
    public function key(): string
    {
        return 'multiselect';
    }

    public function label(): string
    {
        return 'Drop-down (multi)';
    }

    public function icon(): string
    {
        return 'list';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'required', 'choices', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        $element = new Select($field->name);
        $element->setValueOptions($this->extractChoices($field));
        $element->setAttribute('multiple', 'multiple');
        return $element;
    }

    protected function valueForDefault(FieldDefinition $field): mixed
    {
        if ($field->defaultValue === null || $field->defaultValue === '') {
            return null;
        }
        return array_values(array_filter(
            array_map('trim', explode(',', $field->defaultValue)),
            static fn (string $v): bool => $v !== '',
        ));
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
