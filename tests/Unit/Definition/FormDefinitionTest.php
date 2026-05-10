<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Tests\Unit\Definition;

use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FormDefinitionTest extends TestCase
{
    public function testGetAllFieldsFlattensNestedStructure(): void
    {
        $field1 = new FieldDefinition(id: 1, type: 'text', name: 'first_name');
        $field2 = new FieldDefinition(id: 2, type: 'email', name: 'email');
        $field3 = new FieldDefinition(id: 3, type: 'textarea', name: 'message');

        $form = new FormDefinition(
            id:    1,
            slug:  'contact',
            title: 'Contact us',
            sections: [
                new SectionDefinition(
                    id:  1,
                    key: 'main',
                    groups: [
                        new GroupDefinition(
                            id:   1,
                            rows: [
                                new RowDefinition(id: 1, fields: [$field1, $field2]),
                                new RowDefinition(id: 2, fields: [$field3]),
                            ],
                        ),
                    ],
                ),
            ],
        );

        $names = array_map(static fn (FieldDefinition $f): string => $f->name, $form->getAllFields());

        self::assertSame(['first_name', 'email', 'message'], $names);
    }

    public function testDefaultsAreApplied(): void
    {
        $form = new FormDefinition(id: null, slug: 'x', title: 'X');

        self::assertSame(FormDefinition::LAYOUT_SINGLE, $form->layoutMode);
        self::assertSame(FormDefinition::STATUS_ACTIVE, $form->status);
        self::assertSame('Submit', $form->submitLabel);
        self::assertSame('left', $form->submitAlignment);
        self::assertNull($form->retentionDays);
        self::assertSame([], $form->sections);
        self::assertSame([], $form->notifications);
    }
}
