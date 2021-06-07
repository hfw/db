<?php

namespace Helix\DB;

use Helix\DB;

/**
 * Array storage in an extension table.
 *
 * @method static static factory(DB $db, string $name)
 */
class EAV extends Table {

    /**
     * @param DB $db
     * @param string $name
     */
    public function __construct (DB $db, string $name) {
        parent::__construct($db, $name, ['entity', 'attribute', 'value']);
    }

    /**
     * Whether an entity has an attribute.
     *
     * @param int $id
     * @param string $attribute
     * @return bool
     */
    public function exists (int $id, string $attribute): bool {
        $statement = $this->cache(__FUNCTION__, function() {
            return $this->select(['COUNT(*) > 0'])->where('entity = ? AND attribute = ?')->prepare();
        });
        $exists = (bool)$statement([$id, $attribute])->fetchColumn();
        $statement->closeCursor();
        return $exists;
    }

    /**
     * Pivots and self-joins to return a {@link Select} for the `entity` column,
     * matching on attribute and value.
     *
     * @see DB::match()
     *
     * @param array $match `[attribute => value]`. If empty, selects all IDs for entities having at least one attribute.
     * @return Select
     */
    public function find (array $match) {
        $select = $this->select([$this['entity']]);
        $prior = $this;
        foreach ($match as $attribute => $value) {
            $alias = $this->setName("{$this}__{$attribute}");
            $select->join("{$this} AS {$alias}",
                $alias['entity']->isEqual($prior['entity']),
                $alias['attribute']->isEqual($attribute),
                $this->db->match($alias['value'], $value)
            );
            $prior = $alias;
        }
        $select->group($this['entity']);
        return $select;
    }

    /**
     * Returns an entity's attributes.
     *
     * @param int $id
     * @return array `[attribute => value]`
     */
    public function load (int $id): array {
        $statement = $this->cache(__FUNCTION__, function() {
            $select = $this->select(['attribute', 'value']);
            $select->where('entity = ?');
            $select->order('attribute');
            return $select->prepare();
        });
        return $statement([$id])->fetchAll(DB::FETCH_KEY_PAIR);
    }

    /**
     * Returns associative attribute-value arrays for the given IDs.
     *
     * @param int[] $ids
     * @return array[] `[id => attribute => value]
     */
    public function loadAll (array $ids): array {
        if (empty($ids)) {
            return [];
        }
        if (count($ids) === 1) {
            return [current($ids) => $this->load(current($ids))];
        }
        $loadAll = $this->select(['entity', 'attribute', 'value']);
        $loadAll->where($this->db->match('entity', SQL::marks($ids)));
        $loadAll->order('entity, attribute');
        $values = array_fill_keys($ids, []);
        foreach ($loadAll->getEach(array_values($ids)) as $row) {
            $values[$row['entity']][$row['attribute']] = $row['value'];
        }
        return $values;
    }

    /**
     * Upserts an entity's attributes with those given.
     * Stored attributes not given here are pruned.
     *
     * @param int $id
     * @param array $values `[attribute => value]`
     * @return $this
     */
    public function save (int $id, array $values) {
        $this->delete([
            $this['entity']->isEqual($id),
            $this['attribute']->isNotEqual(array_keys($values))
        ]);
        $statement = $this->cache(__FUNCTION__, function() {
            if ($this->db->isSQLite()) {
                return $this->db->prepare(
                    "INSERT INTO {$this} (entity,attribute,value) VALUES (?,?,?)"
                    . " ON CONFLICT (entity,attribute) DO UPDATE SET value=excluded.value"
                );
            }
            return $this->db->prepare(
                "INSERT INTO {$this} (entity,attribute,value) VALUES (?,?,?)"
                . " ON DUPLICATE KEY UPDATE value=VALUES(value)"
            );
        });
        foreach ($values as $attribute => $value) {
            $statement->execute([$id, $attribute, $value]);
        }
        $statement->closeCursor();
        return $this;
    }
}