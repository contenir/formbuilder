<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Conditional;

/**
 * Translates the admin field-edit POST shape into the rule JSON consumed by
 * {@see RuleEvaluator}.
 *
 * Expected POST shape (from `_field-form.phtml`):
 *
 *     conditional[combinator]                   = "all" | "any"
 *     conditional[conditions][i][field]         = "<other field name>"
 *     conditional[conditions][i][op]            = "equals" | "not_equals" | …
 *     conditional[conditions][i][value]         = "<scalar>"
 *
 * Empty rows (no field selected, or no operator) are dropped silently —
 * pre-rendered placeholder rows the user never filled in shouldn't make
 * the persisted rule larger than necessary. Operators that don't take a
 * value ({@see RuleEvaluator::OP_IS_EMPTY}, {@see RuleEvaluator::OP_IS_NOT_EMPTY})
 * have their `value` stripped on the way in.
 *
 * Returns null when no usable conditions remain — null means "always show",
 * so the form ends up unrolling cleanly to a non-conditional field on save.
 */
class ConditionalRulesParser
{
    private const VALID_OPS = [
        RuleEvaluator::OP_EQUALS,
        RuleEvaluator::OP_NOT_EQUALS,
        RuleEvaluator::OP_IS_EMPTY,
        RuleEvaluator::OP_IS_NOT_EMPTY,
        RuleEvaluator::OP_CONTAINS,
    ];

    private const VALUELESS_OPS = [
        RuleEvaluator::OP_IS_EMPTY,
        RuleEvaluator::OP_IS_NOT_EMPTY,
    ];

    /**
     * @param mixed $post
     * @return array<string, mixed>|null
     */
    public static function parse($post): ?array
    {
        if (! is_array($post)) {
            return null;
        }

        $combinator = ($post['combinator'] ?? null) === 'any' ? 'any' : 'all';
        $rawRows    = $post['conditions'] ?? [];
        if (! is_array($rawRows)) {
            return null;
        }

        $conditions = [];
        foreach ($rawRows as $row) {
            $condition = self::normaliseRow($row);
            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        if ($conditions === []) {
            return null;
        }

        return ['show_when' => [$combinator => $conditions]];
    }

    /**
     * @param mixed $row
     * @return array<string, mixed>|null
     */
    private static function normaliseRow($row): ?array
    {
        if (! is_array($row)) {
            return null;
        }
        $field = trim((string) ($row['field'] ?? ''));
        $op    = (string) ($row['op'] ?? '');
        if ($field === '' || ! in_array($op, self::VALID_OPS, true)) {
            return null;
        }

        $condition = ['field' => $field, 'op' => $op];
        if (! in_array($op, self::VALUELESS_OPS, true)) {
            $condition['value'] = (string) ($row['value'] ?? '');
        }
        return $condition;
    }
}
