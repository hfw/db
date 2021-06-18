<?php

/**
 * @record books
 */
class Book extends AbstractEntity
{

    /**
     * @eav books_eav
     * @var string[]
     */
    protected ?array $attributes;

    /**
     * This verifies DateTime storage
     *
     * @col
     */
    protected DateTimeImmutable $published;

    /**
     * This verifies explicit scalar type declaration.
     *
     * @col
     */
    protected string $title;

    /**
     * @return DateTimeImmutable
     */
    public function getPublished(): DateTimeImmutable
    {
        return $this->published;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param DateTimeImmutable $published
     * @return $this
     */
    public function setPublished(DateTimeImmutable $published)
    {
        $this->published = $published;
        return $this;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }
}
