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
trait AttributesTrait
{

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
     * @return scalar[]
     */
    public function getAttributes(): array
    {
        return $this->attributes ?? [];
    }

    /**
     * @param mixed $attr
     * @return bool
     */
    public function offsetExists($attr): bool
    {
        return isset($this->attributes[$attr]);
    }

    /**
     * @param mixed $attr
     * @return null|scalar
     */
    public function offsetGet($attr)
    {
        return $this->attributes[$attr] ?? null;
    }

    /**
     * @param mixed $attr
     * @param null|scalar $value
     */
    public function offsetSet($attr, $value): void
    {
        if (isset($attr)) {
            if (isset($value)) {
                assert(is_scalar($value));
                $this->attributes[$attr] = $value;
            } else {
                $this->offsetUnset($attr);
            }
        } else {
            // appending must not be null.
            // even though missing numeric offsets would yield null when fetched individually,
            // getAttributes() would not have them.
            assert(isset($value));
            $this->attributes[] = $value;
        }
    }

    /**
     * @param mixed $attr
     */
    public function offsetUnset($attr): void
    {
        unset($this->attributes[$attr]);
    }

    /**
     * @param scalar[] $attributes
     * @return $this
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = array_filter($attributes, 'is_scalar');
        return $this;
    }
}
