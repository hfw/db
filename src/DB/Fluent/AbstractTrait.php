<?php

namespace Helix\DB\Fluent;

use Helix\DB;

/**
 * A trait for other traits in this namespace.
 *
 * @internal
 */
trait AbstractTrait
{

    /**
     * @return string
     */
    abstract public function __toString();

    /**
     * @var DB
     */
    protected $db;
}
