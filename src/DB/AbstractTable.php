<?php

namespace Helix\DB;

use ArrayAccess;
use Exception;
use Helix\DB;

/**
 * Uses `ArrayAccess` to produce {@link Column} instances.
 */
abstract class AbstractTable implements ArrayAccess {

    /**
     * Returns the SQL reference qualifier (i.e. the table name)
     *
     * @return string
     */
    abstract public function __toString ();

    /**
     * Columns keyed by name/alias.
     *
     * @return Column[]
     */
    abstract public function getColumns ();

    /**
     * @param int|string $column
     * @return null|Column
     */
    abstract public function offsetGet ($column);

    /**
     * @var DB
     */
    protected $db;

    /**
     * @param DB $db
     */
    public function __construct (DB $db) {
        $this->db = $db;
    }

    /**
     * @param int|string $column
     * @return bool
     */
    public function offsetExists ($column): bool {
        return $this->offsetGet($column) !== null;
    }

    /**
     * Throws.
     *
     * @param void $offset
     * @param void $value
     * @throws Exception
     */
    final public function offsetSet ($offset, $value): void {
        throw new Exception('Tables are immutable.');
    }

    /**
     * Throws.
     *
     * @param void $name
     * @throws Exception
     */
    final public function offsetUnset ($name): void {
        $this->offsetSet($name, null);
    }
}