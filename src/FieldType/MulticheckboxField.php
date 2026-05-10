<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\MultiCheckbox;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class MulticheckboxField extends AbstractFieldType
{
    public function key(): string
    {
        return 'multicheckbox';
    }

    public function label(): string
    {
        return 'Checkbox group';
    }

    public function icon(): string
    {
        return 'checks';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'required', 'choices', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        $element = new MultiCheckbox($field->name);
        $element->setValueOptions($this->extractChoices($field));
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
