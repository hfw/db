<?php

namespace Helix\DB\Fluent;

use Helix\DB;
use Helix\DB\FactoryTrait;

/**
 * A literal expression, exempt from being quoted.
 *
 * @method static static factory(DB $db, string $expression)
 */
class Expression implements ExpressionInterface {

    use FactoryTrait;

    /**
     * @var DB
     */
    protected $db;

    /**
     * @var string
     */
    protected $expression;

    /**
     * @param DB $db
     * @param string $expression
     */
    public function __construct (DB $db, string $expression) {
        $this->db = $db;
        $this->expression = $expression;
    }

    /**
     * @return string
     */
    public function __toString () {
        return $this->expression;
    }
}
