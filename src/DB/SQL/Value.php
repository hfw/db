<?php

namespace Helix\DB\SQL;

use Helix\DB;

/**
 * Represents a value expression. Produces various transformations.
 */
class Value extends Expression implements ValueInterface {

    use ComparisonTrait;
    use AggregateTrait;

    public function __construct (DB $db, string $expression) {
        $this->db = $db;
        parent::__construct($expression);
    }
}