<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Tests\Unit\Service;

use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use Contenir\FormBuilder\Service\TokenReplacer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TokenReplacerTest extends TestCase
{
    public function testReplacesFieldFormAndEntryTokens(): void
    {
        $replacer = new TokenReplacer();
        $form     = new FormDefinition(id: 1, slug: 'contact', title: 'Contact us', description: 'Get in touch');

        $result = $replacer->replace(
            "Hi {field:name}, your {form:title} entry #{entry:id} on {entry:date}",
            $form,
            ['name' => 'Alice'],
            ['id' => 42, 'date' => '2026-05-08'],
        );

        self::assertSame('Hi Alice, your Contact us entry #42 on 2026-05-08', $result);
    }

    public function testLeavesUnknownTokensIntactToSurfaceTypos(): void
    {
        $replacer = new TokenReplacer();
        $form     = new FormDefinition(id: 1, slug: 'x', title: 'X');

        $result = $replacer->replace('Hello {field:missing} {form:nope}', $form, [], []);

        self::assertSame('Hello {field:missing} {form:nope}', $result);
    }

    public function testFlattensArrayFieldValues(): void
    {
        $replacer = new TokenReplacer();
        $form     = new FormDefinition(id: 1, slug: 'x', title: 'X');

        $result = $replacer->replace(
            'Tags: {field:tags}',
            $form,
            ['tags' => ['php', 'forms', 'cms']],
            [],
        );

        self::assertSame('Tags: php, forms, cms', $result);
    }

    public function testCustomNamespaceProvider(): void
    {
        $replacer = new TokenReplacer(['admin_url' => 'https://admin.example']);
        $form     = new FormDefinition(id: 1, slug: 'x', title: 'X');

        $result = $replacer->replace('Visit {site:admin_url}', $form, [], []);

        self::assertSame('Visit https://admin.example', $result);
    }

    public function testReplaceForUrlEncodesResolvedValues(): void
    {
        $replacer = new TokenReplacer();
        $form     = new FormDefinition(id: 1, slug: 'contact', title: 'Contact');

        $result = $replacer->replaceForUrl(
            'https://example.com/thanks?email={field:email}&name={field:name}',
            $form,
            ['email' => 'a@b.co', 'name' => 'Alice & Bob'],
            [],
        );

        // `@` in `a@b.co` becomes `%40`; ampersand and space in `Alice & Bob`
        // become `%20%26%20` — without this encoding the ampersand would
        // close the `name=` query parameter and inject a new one.
        self::assertSame(
            'https://example.com/thanks?email=a%40b.co&name=Alice%20%26%20Bob',
            $result,
        );
    }

    public function testReplaceForUrlLeavesUnresolvedTokensIntact(): void
    {
        $replacer = new TokenReplacer();
        $form     = new FormDefinition(id: 1, slug: 'x', title: 'X');

        // {field:missing} can't resolve — falls through; {form:title} resolves
        // to "X" (no encoding needed since it's already URL-safe).
        $result = $replacer->replaceForUrl(
            'https://x.test/?missing={field:missing}&title={form:title}',
            $form,
            [],
            [],
        );

        self::assertSame('https://x.test/?missing={field:missing}&title=X', $result);
    }

    public function testReplaceForUrlEncodesPathTraversalAttempts(): void
    {
        $replacer = new TokenReplacer();
        $form     = new FormDefinition(id: 1, slug: 'x', title: 'X');

        $result = $replacer->replaceForUrl(
            '/profile/{field:slug}',
            $form,
            ['slug' => '../admin'],
            [],
        );

        // `/` in the substituted value gets encoded as `%2F` — prevents a
        // submitted value from escaping the URL path component.
        self::assertSame('/profile/..%2Fadmin', $result);
    }

    public function testEntryFieldsExpandsToHtmlTableOfLabelsAndValues(): void
    {
        $replacer = new TokenReplacer();
        $form     = $this->formWithFields([
            new FieldDefinition(id: 1, type: 'text', name: 'first_name', label: 'First name'),
            new FieldDefinition(id: 2, type: 'email', name: 'email', label: 'Email'),
            new FieldDefinition(id: 3, type: 'textarea', name: 'comments', label: 'Comments'),
            new FieldDefinition(id: 4, type: 'checkbox', name: 'subscribe', label: 'Subscribe'),
        ]);

        $result = $replacer->replace(
            '<body>{entry:fields}</body>',
            $form,
            [
                'first_name' => 'Alice',
                'email'      => 'alice@example.com',
                'comments'   => "line one\nline two",
                'subscribe'  => '1',
            ],
        );

        self::assertStringContainsString('<table', $result);
        self::assertStringContainsString('First name', $result);
        self::assertStringContainsString('Alice', $result);
        self::assertStringContainsString('alice@example.com', $result);
        // Textarea values get nl2br applied so multi-line input retains breaks.
        self::assertStringContainsString("line one<br />\nline two", $result);
        // Checkbox values render as Yes/No, not 1/0.
        self::assertStringContainsString('>Yes<', $result);
    }

    public function testEntryFieldsRendersEmptyValuesAsEmDash(): void
    {
        $replacer = new TokenReplacer();
        $form     = $this->formWithFields([
            new FieldDefinition(id: 1, type: 'text', name: 'name', label: 'Name'),
            new FieldDefinition(id: 2, type: 'text', name: 'phone', label: 'Phone'),
        ]);

        $result = $replacer->replace('{entry:fields}', $form, ['name' => 'Alice']);

        // Phone wasn't submitted — the row still appears, with an em-dash so
        // the recipient can tell the field exists but wasn't filled in.
        self::assertStringContainsString('Phone', $result);
        self::assertStringContainsString('&mdash;', $result);
    }

    public function testEntryFieldsSkipsHiddenFields(): void
    {
        $replacer = new TokenReplacer();
        $form     = $this->formWithFields([
            new FieldDefinition(id: 1, type: 'text', name: 'name', label: 'Name'),
            new FieldDefinition(id: 2, type: 'hidden', name: 'utm_source', label: 'UTM source'),
        ]);

        $result = $replacer->replace('{entry:fields}', $form, [
            'name'       => 'Alice',
            'utm_source' => 'newsletter',
        ]);

        self::assertStringContainsString('Name', $result);
        self::assertStringNotContainsString('UTM source', $result);
        self::assertStringNotContainsString('newsletter', $result);
    }

    public function testEntryFieldsEscapesHtmlInValues(): void
    {
        $replacer = new TokenReplacer();
        $form     = $this->formWithFields([
            new FieldDefinition(id: 1, type: 'text', name: 'name', label: 'Name'),
        ]);

        $result = $replacer->replace(
            '{entry:fields}',
            $form,
            ['name' => '<script>alert(1)</script>'],
        );

        // Submitted values must be escaped — the raw <script> tag would
        // execute in webmail clients that render HTML email.
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testEntryFieldsReturnsEmptyStringWhenFormHasNoFields(): void
    {
        $replacer = new TokenReplacer();
        $form     = new FormDefinition(id: 1, slug: 'x', title: 'X');

        $result = $replacer->replace('before {entry:fields} after', $form, []);

        self::assertSame('before  after', $result);
    }

    /**
     * @param list<FieldDefinition> $fields
     */
    private function formWithFields(array $fields): FormDefinition
    {
        return new FormDefinition(
            id:    1,
            slug:  'contact',
            title: 'Contact',
            sections: [
                new SectionDefinition(
                    id:   1,
                    key:  'main',
                    groups: [
                        new GroupDefinition(
                            id:   1,
                            rows: [
                                new RowDefinition(id: 1, fields: $fields),
                            ],
                        ),
                    ],
                ),
            ],
        );
    }
}
