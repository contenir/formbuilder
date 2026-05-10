<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Definition;

/**
 * Immutable description of a single validator applied to a field.
 *
 * The {@see $type} value is one of the curated UI keys (`required`,
 * `string_length`, `between`, `email`, `url`, `regex`, `confirm`); the
 * Builder service maps each type onto a Zend validator at form-build time.
 *
 * @phpstan-type ValidatorArray array{type: string, options?: array<string, mixed>, message?: string|null}
 */
final class ValidatorDefinition
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $type,
        public readonly array $options = [],
        public readonly ?string $message = null,
    ) {
    }

    /** @param ValidatorArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            type:    (string) $data['type'],
            options: (array) ($data['options'] ?? []),
            message: isset($data['message']) ? (string) $data['message'] : null,
        );
    }

    /** @return ValidatorArray */
    public function toArray(): array
    {
        return [
            'type'    => $this->type,
            'options' => $this->options,
            'message' => $this->message,
        ];
    }
}
