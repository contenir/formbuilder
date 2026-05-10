<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Tests\Unit\Validator;

use Laminas\Validator\Between;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\Hostname;
use Laminas\Validator\Regex;
use Laminas\Validator\StringLength;
use Contenir\FormBuilder\Definition\ValidatorDefinition;
use Contenir\FormBuilder\Validator\ValidatorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ValidatorFactoryTest extends TestCase
{
    public function testRequiredReturnsNullBecauseItIsHandledOnTheInputFilter(): void
    {
        $factory = new ValidatorFactory();
        self::assertNull($factory->create(new ValidatorDefinition(ValidatorFactory::TYPE_REQUIRED)));
    }

    public function testConfirmReturnsNullBecauseItIsAttachedAsCrossFieldRule(): void
    {
        $factory = new ValidatorFactory();
        self::assertNull($factory->create(new ValidatorDefinition(ValidatorFactory::TYPE_CONFIRM)));
    }

    /** @return array<string, array{string, class-string}> */
    public static function knownTypeProvider(): array
    {
        return [
            'string_length' => [ValidatorFactory::TYPE_STRING_LENGTH, StringLength::class],
            'between'       => [ValidatorFactory::TYPE_BETWEEN, Between::class],
            'email'         => [ValidatorFactory::TYPE_EMAIL, EmailAddress::class],
            'url'           => [ValidatorFactory::TYPE_URL, Hostname::class],
            'regex'         => [ValidatorFactory::TYPE_REGEX, Regex::class],
        ];
    }

    /** @param class-string $expectedClass */
    #[DataProvider('knownTypeProvider')]
    public function testKnownTypesProduceExpectedValidators(string $type, string $expectedClass): void
    {
        $factory   = new ValidatorFactory();
        $validator = $factory->create(new ValidatorDefinition($type, ['min' => 0, 'max' => 5, 'pattern' => '/.*/']));

        self::assertInstanceOf($expectedClass, $validator);
    }

    public function testUnknownTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ValidatorFactory())->create(new ValidatorDefinition('not_a_real_validator'));
    }

    public function testCustomMessageIsApplied(): void
    {
        $factory   = new ValidatorFactory();
        $validator = $factory->create(new ValidatorDefinition(
            ValidatorFactory::TYPE_EMAIL,
            [],
            'Custom email error',
        ));

        self::assertNotNull($validator);
        $validator->isValid('not-an-email');
        self::assertContains('Custom email error', $validator->getMessages());
    }
}
