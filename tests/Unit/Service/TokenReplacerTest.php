<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Tests\Unit\Service;

use Contenir\FormBuilder\Definition\FormDefinition;
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
}
