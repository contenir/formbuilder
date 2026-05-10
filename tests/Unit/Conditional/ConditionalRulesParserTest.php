<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Tests\Unit\Conditional;

use Contenir\FormBuilder\Conditional\ConditionalRulesParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConditionalRulesParserTest extends TestCase
{
    public function testNonArrayInputReturnsNull(): void
    {
        self::assertNull(ConditionalRulesParser::parse('not an array'));
        self::assertNull(ConditionalRulesParser::parse(null));
    }

    public function testEmptyConditionsReturnsNull(): void
    {
        self::assertNull(ConditionalRulesParser::parse(['combinator' => 'all']));
        self::assertNull(ConditionalRulesParser::parse(['combinator' => 'all', 'conditions' => []]));
    }

    public function testRowsMissingFieldOrOpAreDropped(): void
    {
        $out = ConditionalRulesParser::parse([
            'combinator' => 'all',
            'conditions' => [
                ['field' => '', 'op' => 'equals', 'value' => 'x'],
                ['field' => 'a', 'op' => '',      'value' => 'x'],
                ['field' => 'b', 'op' => 'equals', 'value' => 'x'],
            ],
        ]);

        self::assertSame(
            ['show_when' => ['all' => [['field' => 'b', 'op' => 'equals', 'value' => 'x']]]],
            $out,
        );
    }

    public function testInvalidOperatorIsRejected(): void
    {
        $out = ConditionalRulesParser::parse([
            'combinator' => 'all',
            'conditions' => [
                ['field' => 'a', 'op' => 'starts_with', 'value' => 'x'],
            ],
        ]);

        self::assertNull($out);
    }

    public function testValuelessOperatorsStripValue(): void
    {
        $out = ConditionalRulesParser::parse([
            'combinator' => 'all',
            'conditions' => [
                ['field' => 'a', 'op' => 'is_empty', 'value' => 'leftover'],
            ],
        ]);

        self::assertSame(
            ['show_when' => ['all' => [['field' => 'a', 'op' => 'is_empty']]]],
            $out,
        );
    }

    public function testCombinatorDefaultsToAll(): void
    {
        $out = ConditionalRulesParser::parse([
            'conditions' => [['field' => 'a', 'op' => 'equals', 'value' => 'x']],
        ]);

        self::assertArrayHasKey('show_when', $out);
        self::assertArrayHasKey('all', $out['show_when']);
    }

    public function testAnyCombinatorIsHonoured(): void
    {
        $out = ConditionalRulesParser::parse([
            'combinator' => 'any',
            'conditions' => [['field' => 'a', 'op' => 'equals', 'value' => 'x']],
        ]);

        self::assertArrayHasKey('any', $out['show_when']);
    }

    public function testUnknownCombinatorFallsBackToAll(): void
    {
        $out = ConditionalRulesParser::parse([
            'combinator' => 'maybe',
            'conditions' => [['field' => 'a', 'op' => 'equals', 'value' => 'x']],
        ]);

        self::assertArrayHasKey('all', $out['show_when']);
    }

    public function testNonArrayConditionsRowIsSkipped(): void
    {
        $out = ConditionalRulesParser::parse([
            'combinator' => 'all',
            'conditions' => [
                'not-an-array',
                ['field' => 'a', 'op' => 'equals', 'value' => 'x'],
            ],
        ]);

        self::assertCount(1, $out['show_when']['all']);
    }
}
