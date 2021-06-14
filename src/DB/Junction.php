<?php

namespace Helix\DB;

use Helix\DB;
use ReflectionClass;

/**
 * Represents a junction table, derived from an annotated interface.
 *
 * Interface Annotations:
 *
 * - `@junction <TABLE>`
 * - `@foreign <COLUMN> <CLASS FQN>` or `@for <COLUMN> <CLASS FQN>`
 *
 * @method static static factory(DB $db, string $table, array $classes)
 *
 * @TODO Remove the `for` tag, it's vague.
 */
class Junction extends Table {

    protected const RX_JUNCTION = '/\*\h*@junction\h+(?<table>\w+)/i';
    protected const RX_FOREIGN = '/\*\h*@for(eign)?\h+(?<column>\w+)\h+(?<class>\S+)/i';

    /**
     * `[column => class]`
     *
     * @var string[]
     */
    protected $classes = [];

    /**
     * @param DB $db
     * @param string $interface
     * @return Junction
     */
    public static function fromInterface (DB $db, string $interface) {
        $ref = new ReflectionClass($interface);
        assert($ref->isInterface());
        $doc = $ref->getDocComment();
        preg_match(static::RX_JUNCTION, $doc, $junction);
        preg_match_all(static::RX_FOREIGN, $doc, $foreign, PREG_SET_ORDER);
        $classes = array_column($foreign, 'class', 'column');
        return static::factory($db, $junction['table'], $classes);
    }

    /**
     * @param DB $db
     * @param string $table
     * @param string[] $classes
     */
    public function __construct (DB $db, string $table, array $classes) {
        parent::__construct($db, $table, array_keys($classes));
        $this->classes = $classes;
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
    public function findAll (string $key, array $match = []) {
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
    final public function getClass (string $column): string {
        return $this->classes[$column];
    }

    /**
     * @return string[]
     */
    final public function getClasses () {
        return $this->classes;
    }

    /**
     * @param string $column
     * @return Record
     */
    public function getRecord (string $column) {
        return $this->db->getRecord($this->classes[$column]);
    }

    /**
     * @return Record[]
     */
    public function getRecords () {
        return array_map(fn($class) => $this->db->getRecord($class), $this->classes);
    }

    /**
     * `INSERT IGNORE` to link entities.
     *
     * @param int[] $ids Keyed by column.
     * @return int Rows affected.
     */
    public function link (array $ids): int {
        $statement = $this->cache(__FUNCTION__, function() {
            $columns = implode(',', array_keys($this->columns));
            $slots = implode(',', $this->db->slots(array_keys($this->columns)));
            if ($this->db->isSQLite()) {
                $sql = "INSERT OR IGNORE INTO {$this} ({$columns}) VALUES ({$slots})";
            }
            else {
                $sql = "INSERT IGNORE INTO {$this} ({$columns}) VALUES ({$slots})";
            }
            return $this->db->prepare($sql);
        });
        $affected = $statement($ids)->rowCount();
        $statement->closeCursor();
        return $affected;
    }

    /**
     * Alias for {@link delete()}
     *
     * @param array $ids Keyed by Column
     * @return int Rows affected
     */
    public function unlink (array $ids): int {
        return $this->delete($ids);
    }
}