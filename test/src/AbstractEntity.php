<?php

use Helix\DB\AttributesTrait;
use Helix\DB\EntityInterface;

/**
 * An abstract entity with EAV support.
 *
 * Verifies the `@column` annotation.
 */
abstract class AbstractEntity implements EntityInterface, ArrayAccess {

    use AttributesTrait;

    /**
     * @column
     * @var int
     */
    protected $id = 0;

    /**
     * @return int
     */
    final public function getId () {
        return $this->id;
    }
}