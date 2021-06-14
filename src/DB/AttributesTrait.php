<?php

namespace Helix\DB;

/**
 * Forwards `ArrayAccess` to an EAV array property named `$attributes`.
 *
 * `ArrayAccess` must be implemented by the class using this trait.
 * The `$attributes` property must also be defined in the class,
 * and annotated with `@eav <TABLE>`.
 *
 * The instance must have its attributes loaded before use as an array,
 * otherwise existing EAV data may be lost when the instance is saved.
 * Attribute loading is done automatically by {@link Record}.
 *
 * @see Record::load()
 */
trait AttributesTrait {

    /**
     * Override this property with your own annotation.
     *
     * This is nullable, and must remain null until it's used.
     * Once this is an array, {@link Record::save()} will delete any attributes not in the array.
     * So for example, this must not default to an empty array during construction.
     *
     * All of the {@link Record} methods that return/fetch entities will automatically preload these.
     *
     * @eav table_name_here
     * @var string[] This can be changed to other scalar-typed arrays.
     */
    protected ?array $attributes;

    /**
     * @return array
     */
    public function getAttributes (): array {
        return $this->attributes ?? [];
    }

    /**
     * @param mixed $attr
     * @return bool
     */
    public function offsetExists ($attr): bool {
        return isset($this->attributes) and array_key_exists($attr, $this->attributes);
    }

    /**
     * @param mixed $attr
     * @return null|mixed
     */
    public function offsetGet ($attr) {
        return $this->attributes[$attr] ?? null;
    }

    /**
     * @param mixed $attr
     * @param mixed $value
     */
    public function offsetSet ($attr, $value): void {
        if (isset($attr)) {
            $this->attributes[$attr] = $value;
        }
        else {
            $this->attributes[] = $value;
        }
    }

    /**
     * @param mixed $attr
     */
    public function offsetUnset ($attr): void {
        unset($this->attributes[$attr]);
    }

    /**
     * @param array $attributes
     * @return $this
     */
    public function setAttributes (array $attributes) {
        $this->attributes = $attributes;
        return $this;
    }
}