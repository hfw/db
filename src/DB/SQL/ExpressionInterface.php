<?php

namespace Helix\DB\SQL;

/**
 * Marks the instance as a literal SQL expression, exempt from being quoted.
 */
interface ExpressionInterface {

    /**
     * @return string
     */
    public function __toString ();
}