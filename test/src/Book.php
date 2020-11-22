<?php

/**
 * @record Book
 */
class Book extends AbstractEntity {

    /**
     * @eav BookEAV
     * @var array
     */
    protected $attributes;

    /**
     * @col
     * @var string
     */
    protected $title;

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