<?php

namespace Helix\DB;

use Helix\DB;
use Helix\DB\Fluent\Predicate;

/**
 * Array storage in an extension table.
 *
 * @method static static factory(DB $db, string $name, string $type = 'string')
 */
class EAV extends Table
{

    /**
     * The scalar storage type for the `value` column (implied `NOT NULL`).
     *
     * @var string
     */
    protected $type;

    /**
     * @param DB $db
     * @param string $name
     * @param string $type
     */
    public function __construct(DB $db, string $name, string $type = 'string')
    {
        parent::__construct($db, $name, ['entity', 'attribute', 'value']);
        $this->type = $type;
    }

    /**
     * Whether an entity has an attribute.
     *
     * @param int $id
     * @param string $attribute
     * @return bool
     */
    public function exists(int $id, string $attribute): bool
    {
        $statement = $this->cache(__FUNCTION__, function () {
            return $this->select(['COUNT(*) > 0'])->where('entity = ? AND attribute = ?')->prepare();
        });
        $exists = (bool)$statement([$id, $attribute])->fetchColumn();
        $statement->closeCursor();
        return $exists;
    }

    /**
     * Self-joins to return a {@link Select} for the `entity` column,
     * matching on attribute and value.
     *
     * @see Predicate::match()
     *
     * @param array $match `[attribute => value]`. If empty, selects all IDs for entities having at least one attribute.
     * @return Select
     */
    public function findAll(array $match)
    {
        $select = $this->select([$this['entity']]);
        $prior = $this;
        foreach ($match as $attribute => $value) {
            $alias = $this->setName("{$this}__{$attribute}");
            $select->join("{$this} AS {$alias}",
                $alias['entity']->isEqual($prior['entity']),
                $alias['attribute']->isEqual($attribute),
                Predicate::match($this->db, $alias['value'], $value)
            );
            $prior = $alias;
        }
        $select->group($this['entity']);
        return $select;
    }

    /**
     * @return string
     */
    final public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns an entity's attributes.
     *
     * @param int $id
     * @return scalar[] `[attribute => value]`
     */
    public function load(int $id): array
    {
        $statement = $this->cache(__FUNCTION__, function () {
            $select = $this->select(['attribute', 'value']);
            $select->where('entity = ?');
            $select->order('attribute');
            return $select->prepare();
        });
        return array_map([$this, 'setType'], $statement([$id])->fetchAll(DB::FETCH_KEY_PAIR));
    }

    /**
     * Returns associative attribute-value arrays for the given IDs.
     *
     * @param int[] $ids
     * @return scalar[][] `[id => attribute => value]`
     */
    public function loadAll(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        if (count($ids) === 1) {
            return [current($ids) => $this->load(current($ids))];
        }
        $loadAll = $this->select(['entity', 'attribute', 'value'])
            ->where(Predicate::match($this->db, 'entity', $this->db->marks(count($ids))))
            ->order('entity, attribute');
        $values = array_fill_keys($ids, []);
        foreach ($loadAll->getEach(array_values($ids)) as $row) {
            $values[$row['entity']][$row['attribute']] = $this->setType($row['value']);
        }
        return $values;
    }

    /**
     * Upserts an entity's attributes with those given.
     *
     * Stored attributes not given here are pruned.
     *
     * `NULL` attributes are also pruned.
     *
     * @param int $id
     * @param array $values `[attribute => value]`
     * @return $this
     */
    public function save(int $id, array $values)
    {
        // delete stale
        $this->delete([
            $this['entity']->isEqual($id),
            $this['attribute']->isNotEqual(array_keys($values))
        ]);

        // delete nulls
        if ($nulls = array_filter($values, 'is_null')) {
            $values = array_diff_key($values, $nulls);
            $this->delete([
                $this['entity']->isEqual($id),
                $this['attribute']->isEqual(array_keys($nulls))
            ]);
        }

        // upsert each
        $statement = $this->cache(__FUNCTION__, function () {
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

    /**
     * @param scalar $value
     * @return scalar
     */
    protected function setType($value)
    {
        settype($value, $this->type);
        return $value;
    }
}
