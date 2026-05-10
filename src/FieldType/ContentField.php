<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

use Laminas\Form\Element\Hidden;
use Laminas\Form\ElementInterface;
use Contenir\FormBuilder\Definition\FieldDefinition;

/**
 * Static content block — instructional text / HTML rendered inline in
 * the form, not collected as user data.
 *
 * The body is stored on `field->options['html']` and runs through
 * an HTML sanitizer on save (controller) and
 * on render (FormMarkup). FormBuilderService skips this type when
 * assembling Laminas inputs because there's nothing to submit; the
 * createElement implementation returns a Hidden element so the type
 * still satisfies the buildElement contract for any caller that
 * doesn't first check {@see isStatic()}.
 */
class ContentField extends AbstractFieldType
{
    public function key(): string
    {
        return 'content';
    }

    public function label(): string
    {
        return 'Content / instructions';
    }

    public function icon(): string
    {
        return 'note';
    }

    public function isStatic(): bool
    {
        return true;
    }

    public function supportedGroups(): array
    {
        return ['conditional'];
    }

    public function supportedValidators(): array
    {
        return [];
    }

    protected function createElement(FieldDefinition $field): ElementInterface
    {
        return new Hidden($field->name);
    }
}
