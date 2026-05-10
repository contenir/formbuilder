<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\FieldType;

/**
 * Lookup table of available field types, keyed by {@see FieldTypeInterface::key()}.
 *
 * The default constructor wires up the built-in catalogue. Additional types
 * can be registered programmatically via {@see register()} — that hook is
 * what lets future modules (or tests) extend the type vocabulary without
 * editing this class.
 *
 * @throws \OutOfBoundsException From {@see get()} when the requested key is unknown.
 */
class FieldTypeRegistry
{
    /** @var array<string, FieldTypeInterface> */
    private array $types = [];

    /** @param iterable<FieldTypeInterface>|null $extras */
    public function __construct(?iterable $extras = null)
    {
        foreach (self::defaultTypes() as $type) {
            $this->register($type);
        }

        if ($extras !== null) {
            foreach ($extras as $extra) {
                $this->register($extra);
            }
        }
    }

    /** @return list<FieldTypeInterface> */
    public static function defaultTypes(): array
    {
        return [
            new TextField(),
            new TextareaField(),
            new EmailField(),
            new UrlField(),
            new TelField(),
            new NumberField(),
            new DateField(),
            new DateTimeField(),
            new TimeField(),
            new SelectField(),
            new MultiselectField(),
            new RadioField(),
            new CheckboxField(),
            new MulticheckboxField(),
            new FileField(),
            new HiddenField(),
            new ContentField(),
        ];
    }

    public function register(FieldTypeInterface $type): void
    {
        $this->types[$type->key()] = $type;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @throws \OutOfBoundsException If $key is not registered.
     */
    public function get(string $key): FieldTypeInterface
    {
        if (! isset($this->types[$key])) {
            throw new \OutOfBoundsException(sprintf('Unknown field type "%s"', $key));
        }
        return $this->types[$key];
    }

    /** @return list<FieldTypeInterface> */
    public function all(): array
    {
        return array_values($this->types);
    }

    /** @return list<FieldTypeInterface> */
    public function userSelectable(): array
    {
        return array_values(array_filter(
            $this->types,
            static fn (FieldTypeInterface $type): bool => $type->isUserSelectable()
        ));
    }
}
