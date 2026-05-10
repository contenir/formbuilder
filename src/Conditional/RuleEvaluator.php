<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Conditional;

/**
 * Evaluates conditional-visibility rules persisted on a {@see \Contenir\FormBuilder\Definition\FieldDefinition}.
 *
 * Rule shape:
 *
 * ```json
 * {
 *   "show_when": {
 *     "all": [
 *       { "field": "contact_method", "op": "equals", "value": "email" }
 *     ]
 *   }
 * }
 * ```
 *
 * Combinators: `all` (AND), `any` (OR). Operators: `equals`, `not_equals`,
 * `is_empty`, `is_not_empty`, `contains`. Missing target fields evaluate
 * as null — `equals` against a missing field is false; `is_empty` is true.
 *
 * The evaluator is intentionally pure so the same logic can be mirrored in
 * `src/js/components/FormConditional.js` for client-side show/hide. Any
 * change to operator semantics here must be reflected there too.
 */
class RuleEvaluator
{
    public const OP_EQUALS       = 'equals';
    public const OP_NOT_EQUALS   = 'not_equals';
    public const OP_IS_EMPTY     = 'is_empty';
    public const OP_IS_NOT_EMPTY = 'is_not_empty';
    public const OP_CONTAINS     = 'contains';

    /**
     * UI-facing operator vocabulary. Each entry's `needs_value` flag tells the
     * editor whether to render a value input alongside the operator dropdown
     * — `is_empty` / `is_not_empty` ignore any submitted value.
     *
     * @return list<array{op: string, label: string, needs_value: bool}>
     */
    public static function operatorVocabulary(): array
    {
        return [
            ['op' => self::OP_EQUALS,       'label' => 'is equal to',     'needs_value' => true],
            ['op' => self::OP_NOT_EQUALS,   'label' => 'is not equal to', 'needs_value' => true],
            ['op' => self::OP_CONTAINS,     'label' => 'contains',        'needs_value' => true],
            ['op' => self::OP_IS_EMPTY,     'label' => 'is empty',        'needs_value' => false],
            ['op' => self::OP_IS_NOT_EMPTY, 'label' => 'is not empty',    'needs_value' => false],
        ];
    }

    /**
     * @param array<string, mixed>|null $rule
     * @param array<string, mixed>      $values
     */
    public function shouldShow(?array $rule, array $values): bool
    {
        if ($rule === null || $rule === []) {
            return true;
        }

        $showWhen = $rule['show_when'] ?? null;
        if (! is_array($showWhen) || $showWhen === []) {
            return true;
        }

        $combinator = isset($showWhen['any']) ? 'any' : 'all';
        $conditions = $showWhen[$combinator] ?? [];
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        if ($combinator === 'any') {
            foreach ($conditions as $condition) {
                if (is_array($condition) && $this->evaluate($condition, $values)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition) || ! $this->evaluate($condition, $values)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $values
     */
    private function evaluate(array $condition, array $values): bool
    {
        $field = (string) ($condition['field'] ?? '');
        $op    = (string) ($condition['op'] ?? '');
        if ($field === '' || $op === '') {
            return false;
        }

        $current  = $values[$field] ?? null;
        $expected = $condition['value'] ?? null;

        return match ($op) {
            self::OP_EQUALS       => $this->stringEquals($current, $expected),
            self::OP_NOT_EQUALS   => ! $this->stringEquals($current, $expected),
            self::OP_IS_EMPTY     => $this->isEmpty($current),
            self::OP_IS_NOT_EMPTY => ! $this->isEmpty($current),
            self::OP_CONTAINS     => $this->stringContains($current, $expected),
            default               => false,
        };
    }

    private function stringEquals(mixed $current, mixed $expected): bool
    {
        if (is_array($current)) {
            $expectedString = is_scalar($expected) ? (string) $expected : '';
            foreach ($current as $entry) {
                if (is_scalar($entry) && (string) $entry === $expectedString) {
                    return true;
                }
            }
            return false;
        }
        return $this->scalarToString($current) === $this->scalarToString($expected);
    }

    private function stringContains(mixed $current, mixed $expected): bool
    {
        $needle = $this->scalarToString($expected);
        if ($needle === '') {
            return false;
        }
        if (is_array($current)) {
            foreach ($current as $entry) {
                if (is_scalar($entry) && str_contains((string) $entry, $needle)) {
                    return true;
                }
            }
            return false;
        }
        return str_contains($this->scalarToString($current), $needle);
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_array($value)) {
            return $value === [];
        }
        if (is_scalar($value)) {
            return (string) $value === '';
        }
        return true;
    }

    private function scalarToString(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        return is_scalar($value) ? (string) $value : '';
    }
}
