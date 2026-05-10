<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Definition;

/**
 * Top-level layout container within a form.
 *
 * Sections are always semantic: {@see $key} is a stable machine identifier
 * (used in multi-step navigation when the parent form's `layout_mode` is
 * `stepped`); {@see $legend} is the optional human label rendered above
 * the section's groups.
 */
final class SectionDefinition
{
    /** @param list<GroupDefinition> $groups */
    public function __construct(
        public readonly ?int $id,
        public readonly string $key,
        public readonly ?string $legend = null,
        public readonly ?string $description = null,
        public readonly int $sort = 0,
        public readonly array $groups = [],
    ) {
    }
}
