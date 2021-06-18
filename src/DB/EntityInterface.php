<?php

namespace Helix\DB;

/**
 * Exposes the object's ID for storage.
 */
interface EntityInterface
{

    /**
     * @return int
     */
    public function getId();
}
