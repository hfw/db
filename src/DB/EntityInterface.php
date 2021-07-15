<?php

namespace Helix\DB;

/**
 * Exposes the object's auto-increment ID.
 */
interface EntityInterface
{

    /**
     * The entity's auto-increment ID, or zero if it doesn't have one yet.
     *
     * @return int
     */
    public function getId(): int;
}
