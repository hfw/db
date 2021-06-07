<?php

namespace Helix\DB;

use Helix\DB;

/**
 * Scoped transaction/savepoint.
 *
 * If the instance isn't committed before it loses scope, it's rolled back.
 * There is no `rollback()` method.
 *
 * In order to ensure proper destruction, the instance MUST NOT leave the scope it's created in.
 *
 * @method static static factory(DB $db)
 */
class Transaction {

    use FactoryTrait;

    /**
     * @var bool
     */
    protected $committed = false;

    /**
     * @var DB
     */
    protected $db;

    /**
     * Begins the transaction/savepoint.
     *
     * @param DB $db
     */
    public function __construct (DB $db) {
        $this->db = $db;
        $db->beginTransaction();
    }

    /**
     * Rolls back if the instance wasn't committed.
     */
    public function __destruct () {
        if (!$this->committed) {
            $this->db->rollBack();
        }
    }

    /**
     * Commits the transaction/savepoint.
     *
     * This is safe to call multiple times, it won't have any effect after the first time.
     *
     * @return true
     */
    public function commit (): bool {
        return $this->committed or $this->committed = $this->db->commit();
    }

    /**
     * @return bool
     */
    final public function wasCommitted (): bool {
        return $this->committed;
    }
}