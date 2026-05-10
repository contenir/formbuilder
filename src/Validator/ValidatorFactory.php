<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Validator;

use Laminas\Validator\Between;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\Hostname;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Laminas\Validator\ValidatorInterface;
use Contenir\FormBuilder\Definition\ValidatorDefinition;

/**
 * Maps the curated UI validator vocabulary onto Laminas validator instances.
 *
 * Keeping the user-facing keys decoupled from concrete classes guards the
 * builder UI against breaking changes in the validator library and lets the
 * vocabulary stay small. Unknown keys throw — the builder never persists a
 * validator type the registry can't resolve, so this is a defensive guard
 * against hand-edited rows.
 *
 * @throws \InvalidArgumentException From {@see create()} when the type is unknown.
 */
class ValidatorFactory
{
    public const TYPE_REQUIRED      = 'required';
    public const TYPE_STRING_LENGTH = 'string_length';
    public const TYPE_BETWEEN       = 'between';
    public const TYPE_EMAIL         = 'email';
    public const TYPE_URL           = 'url';
    public const TYPE_REGEX         = 'regex';
    public const TYPE_CONFIRM       = 'confirm';

    /**
     * @return list<array{type: string, label: string, requires_options: bool}>
     */
    /**
     * The UI vocabulary of validator types — the field-edit form lists each
     * one as a checkbox plus its options inputs. {@see TYPE_REQUIRED} is
     * deliberately omitted because the field-edit form has a dedicated
     * top-level "Required" checkbox that already drives the input filter;
     * exposing it again here is the duplication the type-aware editor was
     * built to remove. The constant remains because legacy persisted
     * fields (and {@see FormBuilderService}) still recognise the value.
     */
    public static function vocabulary(): array
    {
        return [
            ['type' => self::TYPE_STRING_LENGTH, 'label' => 'Min / max length',          'requires_options' => true],
            ['type' => self::TYPE_BETWEEN,       'label' => 'Number range',              'requires_options' => true],
            ['type' => self::TYPE_EMAIL,         'label' => 'Email address',             'requires_options' => false],
            ['type' => self::TYPE_URL,           'label' => 'URL',                       'requires_options' => false],
            ['type' => self::TYPE_REGEX,         'label' => 'Pattern (regex)',           'requires_options' => true],
            ['type' => self::TYPE_CONFIRM,       'label' => 'Must match another field', 'requires_options' => true],
        ];
    }

    /**
     * Returns null when the type does not produce a Laminas validator
     * ({@see TYPE_REQUIRED} is handled via the input filter; {@see TYPE_CONFIRM}
     * is wired by {@see \Contenir\FormBuilder\Service\FormBuilderService} as a
     * cross-field rule).
     */
    public function create(ValidatorDefinition $definition): ?ValidatorInterface
    {
        $validator = match ($definition->type) {
            self::TYPE_REQUIRED      => null,
            self::TYPE_STRING_LENGTH => $this->stringLength($definition->options),
            self::TYPE_BETWEEN       => $this->between($definition->options),
            self::TYPE_EMAIL         => new EmailAddress(),
            self::TYPE_URL           => new Hostname(),
            self::TYPE_REGEX         => $this->regex($definition->options),
            self::TYPE_CONFIRM       => null,
            default                  => throw new \InvalidArgumentException(
                sprintf('Unknown validator type "%s"', $definition->type)
            ),
        };

        if ($validator !== null && $definition->message !== null && $definition->message !== '') {
            $validator->setMessage($definition->message);
        }

        return $validator;
    }

    /** @param array<string, mixed> $options */
    private function stringLength(array $options): StringLength
    {
        $min = isset($options['min']) ? (int) $options['min'] : 0;
        $max = isset($options['max']) ? (int) $options['max'] : null;
        return new StringLength([
            'min' => $min,
            'max' => $max,
        ]);
    }

    /** @param array<string, mixed> $options */
    private function between(array $options): Between
    {
        return new Between([
            'min'       => $options['min'] ?? 0,
            'max'       => $options['max'] ?? PHP_INT_MAX,
            'inclusive' => (bool) ($options['inclusive'] ?? true),
        ]);
    }

    /** @param array<string, mixed> $options */
    private function regex(array $options): Regex
    {
        $pattern = (string) ($options['pattern'] ?? '/.*/');
        return new Regex(['pattern' => $pattern]);
    }
}
