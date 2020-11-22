<?php

namespace Helix\DB\SQL;

/**
 * A literal expression, exempt from being quoted.
 */
class Expression implements ExpressionInterface {

    /**
     * @var string
     */
    protected $expression;

    /**
     * @param string $expression
     */
    public function __construct (string $expression) {
        $this->expression = $expression;
    }

    /**
     * @return string
     */
    public function __toString () {
        return $this->expression;
    }
}