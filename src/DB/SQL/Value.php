<?php

namespace Helix\DB\SQL;

/**
 * Represents a value expression. Produces various transformations.
 */
class Value extends Expression implements ValueInterface {

    use ComparisonTrait;
    use AggregateTrait;
}