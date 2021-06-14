<?php

/**
 * @record books
 */
class Book extends AbstractEntity {

    /**
     * @eav books_eav
     * @var string[]
     */
    protected ?array $attributes;

    /**
     * This verifies explicit type declaration.
     *
     * @col
     */
    protected string $title;

    /**
     * @return string
     */
    public function getTitle () {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle ($title) {
        $this->title = $title;
        return $this;
    }
}