<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Service;

use Contenir\FormBuilder\Definition\FormDefinition;

/**
 * Substitutes `{namespace:key}` merge tags inside notification templates.
 *
 * Built-in namespaces:
 *  - `field:<name>`  → submitted value for the named field
 *  - `form:<attr>`   → form-level attribute (title, slug, description)
 *  - `entry:<attr>`  → entry attribute (id, date, ip, status)
 *  - `site:<attr>`   → site-level attribute (admin_url, base_url)
 *
 * Unknown tokens are left intact so they remain visible to the recipient
 * rather than silently disappearing — this surfaces typos in templates.
 */
class TokenReplacer
{
    /** @var array<string, callable(string): string> */
    private array $providers = [];

    /**
     * @param array<string, mixed> $siteContext  Static values for `site:*` tokens.
     */
    public function __construct(array $siteContext = [])
    {
        if ($siteContext !== []) {
            $this->register('site', static function (string $key) use ($siteContext): string {
                return isset($siteContext[$key]) ? (string) $siteContext[$key] : '';
            });
        }
    }

    /**
     * Register an additional namespace handler. Useful in tests and custom extensions.
     *
     * @param callable(string): string $resolver
     */
    public function register(string $namespace, callable $resolver): void
    {
        $this->providers[$namespace] = $resolver;
    }

    /**
     * @param array<string, mixed> $values   field name => submitted value
     * @param array<string, mixed> $entry    entry attributes (id, date, ip, status)
     */
    public function replace(string $template, FormDefinition $form, array $values, array $entry = []): string
    {
        return $this->dispatch($template, $form, $values, $entry, null);
    }

    /**
     * Same as {@see replace()} but URL-encodes resolved values via
     * `rawurlencode`. Use when a template is being expanded into a URL
     * (e.g. a custom redirect URL) so submitted field values can't
     * smuggle query separators or path traversal sequences.
     *
     * Tokens that fall through (unknown namespace/key) are left intact
     * and not encoded — encoding `{field:foo}` would corrupt the URL
     * for legitimate authors who haven't yet renamed a field.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $entry
     */
    public function replaceForUrl(string $template, FormDefinition $form, array $values, array $entry = []): string
    {
        return $this->dispatch($template, $form, $values, $entry, 'rawurlencode');
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $entry
     * @param (callable(string): string)|null $postProcess
     */
    private function dispatch(
        string $template,
        FormDefinition $form,
        array $values,
        array $entry,
        ?callable $postProcess,
    ): string {
        if ($template === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{(?<ns>[a-z]+):(?<key>[a-z0-9_\-\.]+)\}/i',
            function (array $matches) use ($form, $values, $entry, $postProcess): string {
                $namespace = strtolower((string) $matches['ns']);
                $key       = (string) $matches['key'];
                $original  = $matches[0];

                $resolved = match ($namespace) {
                    'field' => $this->resolveField($values, $key, $original),
                    'form'  => $this->resolveForm($form, $key, $original),
                    'entry' => $this->resolveEntry($entry, $key, $original),
                    default => $this->resolveCustom($namespace, $key, $original),
                };

                if ($postProcess === null || $resolved === $original) {
                    return $resolved;
                }

                return $postProcess($resolved);
            },
            $template
        );
    }

    /** @param array<string, mixed> $values */
    private function resolveField(array $values, string $key, string $original): string
    {
        if (! array_key_exists($key, $values)) {
            return $original;
        }
        $value = $values[$key];
        if (is_array($value)) {
            $flat = array_filter($value, static fn ($v): bool => is_scalar($v));
            return implode(', ', array_map(static fn ($v): string => (string) $v, $flat));
        }
        return is_scalar($value) ? (string) $value : '';
    }

    private function resolveForm(FormDefinition $form, string $key, string $original): string
    {
        return match ($key) {
            'title'       => $form->title,
            'slug'        => $form->slug,
            'description' => $form->description ?? '',
            default       => $original,
        };
    }

    /** @param array<string, mixed> $entry */
    private function resolveEntry(array $entry, string $key, string $original): string
    {
        if (! array_key_exists($key, $entry)) {
            return $original;
        }
        $value = $entry[$key];
        return is_scalar($value) ? (string) $value : $original;
    }

    private function resolveCustom(string $namespace, string $key, string $original): string
    {
        if (! isset($this->providers[$namespace])) {
            return $original;
        }
        $value = ($this->providers[$namespace])($key);
        return $value === '' ? $original : $value;
    }
}
