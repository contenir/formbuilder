<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Tests\Unit\FieldType;

use Laminas\Form\Element\Text;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\FieldType\AbstractFieldType;
use Contenir\FormBuilder\FieldType\FieldTypeInterface;
use Contenir\FormBuilder\FieldType\FieldTypeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FieldTypeRegistryTest extends TestCase
{
    public function testCatalogueIncludesCoreTypes(): void
    {
        $registry = new FieldTypeRegistry();

        $expected = ['text', 'textarea', 'email', 'url', 'tel', 'number', 'date', 'select', 'checkbox', 'hidden'];
        foreach ($expected as $key) {
            self::assertTrue($registry->has($key), sprintf('Type "%s" should be registered', $key));
        }
    }

    public function testGetThrowsForUnknownKey(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        (new FieldTypeRegistry())->get('non_existent');
    }

    public function testCanRegisterAdditionalType(): void
    {
        $registry = new FieldTypeRegistry();
        $registry->register($this->customType('extra', false));

        self::assertTrue($registry->has('extra'));
        self::assertFalse(in_array(
            'extra',
            array_map(static fn (FieldTypeInterface $t): string => $t->key(), $registry->userSelectable()),
            true,
        ));
    }

    private function customType(string $key, bool $userSelectable): FieldTypeInterface
    {
        return new class ($key, $userSelectable) extends AbstractFieldType {
            public function __construct(private string $k, private bool $sel)
            {
            }

            public function key(): string
            {
                return $this->k;
            }

            public function label(): string
            {
                return $this->k;
            }

            public function isUserSelectable(): bool
            {
                return $this->sel;
            }

            protected function createElement(FieldDefinition $field): ElementInterface
            {
                return new Text($field->name);
            }
        };
    }
}
