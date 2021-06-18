<?php

namespace Helix\DB\Fluent;

/**
 * Marks the instance as a literal SQL expression, exempt from being quoted.
 */
interface ExpressionInterface
{

    /**
     * @return string
     */
    public function __toString();
}
