<?php

namespace Helix\DB\SQL;

/**
 * Represents a value expression. Can produce various transformations.
 */
class Value extends Expression implements ValueInterface {

    use AggregateTrait;
    use CastTrait;
    use ComparisonTrait;
}