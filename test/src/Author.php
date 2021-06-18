<?php

/**
 * Verifies the `@record`, `@col`, and `@eav` annotations.
 *
 * @record authors
 */
class Author extends AbstractEntity
{

    /**
     * @eav authors_eav
     * @var string[]
     */
    protected ?array $attributes;

    /**
     * @col
     * @var string
     */
    protected $name;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
}
