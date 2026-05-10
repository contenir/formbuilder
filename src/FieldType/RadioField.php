<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Radio;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

class RadioField extends AbstractFieldType
{
    public function key(): string
    {
        return 'radio';
    }

    public function label(): string
    {
        return 'Radio group';
    }

    public function icon(): string
    {
        return 'circle-check';
    }

    public function supportedGroups(): array
    {
        return ['label', 'visibility', 'description', 'required', 'choices', 'conditional'];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        $element = new Radio($field->name);
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
