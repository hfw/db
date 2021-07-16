<?php

namespace Helix\DB;

use Helix\DB;

/**
 * Represents a junction table, derived from an annotated interface.
 *
 * Interface Annotations:
 *
 * - `@junction <TABLE>`
 * - `@foreign <COLUMN> <CLASS FQN>`
 *
 * @method static static factory(DB $db, string $interface)
 */
class Junction extends Table
{

    /**
     * `[column => class]`
     *
     * @var string[]
     */
    protected $classes = [];

    /**
     * @var Reflection
     */
    protected $ref;

    /**
     * @param DB $db
     * @param string $interface
     */
    public function __construct(DB $db, string $interface)
    {
        $this->ref = Reflection::factory($db, $interface);
        $this->classes = $this->ref->getForeignClasses();
        parent::__construct($db, $this->ref->getJunctionTable(), array_keys($this->classes));
    }

    /**
     * Returns a {@link Select} for entities referenced by a foreign key.
     *
     * The {@link Select} is literal and can be iterated directly.
     *
     * @param string $key The column referencing the class to collect.
     * @param array $match Keyed by junction column.
     * @return Select|EntityInterface[]
     */
    public function findAll(string $key, array $match = [])
    {
        $record = $this->getRecord($key);
        $select = $record->loadAll();
        $select->join($this, $this[$key]->isEqual($record['id']));
        foreach ($match as $a => $b) {
            $select->where($this->db->match($this[$a], $b));
        }
        return $select;
    }

    /**
     * @param string $column
     * @return string
     */
    final public function getClass(string $column): string
    {
        return $this->classes[$column];
    }

    /**
     * @return string[]
     */
    final public function getClasses()
    {
        return $this->classes;
    }

    /**
     * @param string $column
     * @return Record
     */
    public function getRecord(string $column)
    {
        return $this->db->getRecord($this->classes[$column]);
    }

    /**
     * @return Record[]
     */
    public function getRecords()
    {
        return array_map(fn($class) => $this->db->getRecord($class), $this->classes);
    }

    /**
     * `INSERT IGNORE` to link entities.
     *
     * @param int[]|EntityInterface[] $ids Keyed by column
     * @return int Rows affected
     */
    public function link(array $ids): int
    {
        $statement = $this->cache(__FUNCTION__, function () {
            $columns = implode(',', array_keys($this->columns));
            $slots = implode(',', $this->db->slots(array_keys($this->columns)));
            if ($this->db->isSQLite()) {
                $sql = "INSERT OR IGNORE INTO {$this} ({$columns}) VALUES ({$slots})";
            } else {
                $sql = "INSERT IGNORE INTO {$this} ({$columns}) VALUES ({$slots})";
            }
            return $this->db->prepare($sql);
        });
        $ids = array_map(fn($id) => $id instanceof EntityInterface ? $id->getId() : $id, $ids);
        $affected = $statement($ids)->rowCount();
        $statement->closeCursor();
        return $affected;
    }

    /**
     * Typed alias for {@link delete()}
     *
     * @param int[]|EntityInterface[] $ids Keyed by column
     * @return int Rows affected
     */
    public function unlink(array $ids): int
    {
        return $this->delete($ids);
    }
}
