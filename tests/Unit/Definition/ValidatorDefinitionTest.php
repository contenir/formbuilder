<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Tests\Unit\Definition;

use Contenir\FormBuilder\Definition\ValidatorDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ValidatorDefinitionTest extends TestCase
{
    public function testRoundTripsThroughArray(): void
    {
        $definition = ValidatorDefinition::fromArray([
            'type'    => 'string_length',
            'options' => ['min' => 1, 'max' => 5],
            'message' => 'Too long',
        ]);

        self::assertSame('string_length', $definition->type);
        self::assertSame(['min' => 1, 'max' => 5], $definition->options);
        self::assertSame('Too long', $definition->message);
        self::assertSame(
            ['type' => 'string_length', 'options' => ['min' => 1, 'max' => 5], 'message' => 'Too long'],
            $definition->toArray()
        );
    }

    public function testDefaultsWhenMessageOmitted(): void
    {
        $definition = ValidatorDefinition::fromArray(['type' => 'email']);

        self::assertSame('email', $definition->type);
        self::assertSame([], $definition->options);
        self::assertNull($definition->message);
    }
}
