<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Tests\Unit\Conditional;

use Contenir\FormBuilder\Conditional\RuleEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RuleEvaluatorTest extends TestCase
{
    public function testNullRuleAlwaysShows(): void
    {
        self::assertTrue((new RuleEvaluator())->shouldShow(null, []));
    }

    public function testEmptyRuleAlwaysShows(): void
    {
        self::assertTrue((new RuleEvaluator())->shouldShow([], ['x' => 'y']));
    }

    public function testRuleWithoutShowWhenAlwaysShows(): void
    {
        self::assertTrue((new RuleEvaluator())->shouldShow(['something' => 'else'], []));
    }

    public function testEqualsMatchesAndDifferingValueDoesNot(): void
    {
        $rule = ['show_when' => ['all' => [['field' => 'method', 'op' => 'equals', 'value' => 'email']]]];

        self::assertTrue((new RuleEvaluator())->shouldShow($rule, ['method' => 'email']));
        self::assertFalse((new RuleEvaluator())->shouldShow($rule, ['method' => 'phone']));
    }

    public function testEqualsAgainstMissingFieldFailsClosed(): void
    {
        $rule = ['show_when' => ['all' => [['field' => 'method', 'op' => 'equals', 'value' => 'email']]]];

        self::assertFalse((new RuleEvaluator())->shouldShow($rule, []));
    }

    public function testNotEqualsAgainstMissingFieldMatches(): void
    {
        $rule = ['show_when' => ['all' => [['field' => 'method', 'op' => 'not_equals', 'value' => 'email']]]];

        self::assertTrue((new RuleEvaluator())->shouldShow($rule, []));
    }

    /** @return list<array{string, mixed, mixed, bool}> */
    public static function operatorMatrix(): array
    {
        return [
            ['equals',       'a',  'a',  true],
            ['equals',       'a',  'b',  false],
            ['equals',       1,    '1',  true],
            ['equals',       null, '',   true],
            ['not_equals',   'a',  'b',  true],
            ['not_equals',   'a',  'a',  false],
            ['is_empty',     '',   null, true],
            ['is_empty',     'x',  null, false],
            ['is_empty',     null, null, true],
            ['is_empty',     [],   null, true],
            ['is_not_empty', 'x',  null, true],
            ['is_not_empty', '',   null, false],
            ['contains',     'hello world', 'world', true],
            ['contains',     'hello',       'world', false],
            ['contains',     'hello',       '',      false],
        ];
    }

    #[DataProvider('operatorMatrix')]
    public function testOperatorBehaviour(string $op, mixed $current, mixed $expected, bool $shouldMatch): void
    {
        $rule = ['show_when' => ['all' => [['field' => 'x', 'op' => $op, 'value' => $expected]]]];

        self::assertSame(
            $shouldMatch,
            (new RuleEvaluator())->shouldShow($rule, ['x' => $current]),
        );
    }

    public function testAllCombinatorRequiresEveryConditionToMatch(): void
    {
        $rule = ['show_when' => ['all' => [
            ['field' => 'a', 'op' => 'equals', 'value' => '1'],
            ['field' => 'b', 'op' => 'equals', 'value' => '2'],
        ]]];

        self::assertTrue((new RuleEvaluator())->shouldShow($rule, ['a' => '1', 'b' => '2']));
        self::assertFalse((new RuleEvaluator())->shouldShow($rule, ['a' => '1', 'b' => '3']));
    }

    public function testAnyCombinatorRequiresAtLeastOneToMatch(): void
    {
        $rule = ['show_when' => ['any' => [
            ['field' => 'a', 'op' => 'equals', 'value' => '1'],
            ['field' => 'b', 'op' => 'equals', 'value' => '2'],
        ]]];

        self::assertTrue((new RuleEvaluator())->shouldShow($rule, ['a' => '1', 'b' => '0']));
        self::assertTrue((new RuleEvaluator())->shouldShow($rule, ['a' => '0', 'b' => '2']));
        self::assertFalse((new RuleEvaluator())->shouldShow($rule, ['a' => '0', 'b' => '0']));
    }

    public function testEqualsAgainstArrayValueMatchesIfAnyEntryMatches(): void
    {
        $rule = ['show_when' => ['all' => [['field' => 'tags', 'op' => 'equals', 'value' => 'php']]]];

        self::assertTrue((new RuleEvaluator())->shouldShow($rule, ['tags' => ['php', 'js']]));
        self::assertFalse((new RuleEvaluator())->shouldShow($rule, ['tags' => ['ruby', 'js']]));
    }

    public function testUnknownOperatorFailsClosed(): void
    {
        $rule = ['show_when' => ['all' => [['field' => 'x', 'op' => 'starts_with', 'value' => 'foo']]]];

        self::assertFalse((new RuleEvaluator())->shouldShow($rule, ['x' => 'foobar']));
    }
}
