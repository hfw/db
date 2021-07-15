<?php

use Helix\DB\AttributesTrait;
use Helix\DB\EntityInterface;

/**
 * An example of an abstract entity with EAV support.
 *
 * Verifies the `@column` annotation.
 */
abstract class AbstractEntity implements EntityInterface, ArrayAccess
{

    use AttributesTrait;

    /**
     * @column
     * @var int
     */
    protected $id = 0;

    /**
     * @return int
     */
    final public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int|EntityInterface $entity
     * @return bool
     */
    final public function is($entity): bool
    {
        $entity = $entity instanceof EntityInterface ? $entity->getId() : (int)$entity;
        return $this->id === $entity;
    }
}
