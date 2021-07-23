<?php

namespace Helix\DB;

use Generator;
use Helix\DB;
use Helix\DB\Fluent\Predicate;
use Helix\DB\Record\Serializer;

/**
 * Represents an "active record" table, derived from an annotated class implementing {@link EntityInterface}.
 *
 * Class Annotations:
 *
 * - `@record TABLE`
 *
 * Property Annotations:
 *
 * - `@col` or `@column`
 * - `@unique` or `@unique <SHARED_IDENTIFIER>` for a single or multi-column unique-key.
 *  - The shared identifier must be alphabetical, allowing underscores.
 *  - The identifier can be arbitrary, but it's necessary in order to associate component properties.
 *  - The column/s may be nullable; MySQL and SQLite don't enforce uniqueness for NULL.
 * - `@eav <TABLE>`
 *
 * Property types are preserved.
 * Properties which are objects can be dehydrated/rehydrated if they're strictly typed.
 * Strict typing is preferred, but annotations and finally default values are used as fallbacks.
 *
 * > Annotating the types `String` (capital "S") or `STRING` (all caps) results in `TEXT` and `BLOB`
 *
 * @method static static factory(DB $db, string|EntityInterface $class)
 */
class Record extends Table
{

    /**
     * The number of entities to load EAV entries for at a time,
     * during {@link Record::fetchEach()} iteration.
     */
    protected const EAV_BATCH_LOAD = 256;

    /**
     * `[property => EAV]`
     *
     * @var EAV[]
     */
    protected $eav = [];

    /**
     * A boilerplate instance of the class, to clone and populate.
     *
     * @var EntityInterface
     */
    protected $proto;

    protected Serializer $serializer;

    /**
     * @param DB $db
     * @param string|EntityInterface $class
     */
    public function __construct(DB $db, $class)
    {
        $this->serializer = Serializer::factory($db, $class);
        $this->proto = is_object($class) ? $class : $this->serializer->newProto();
        assert($this->proto instanceof EntityInterface);
        $this->eav = $this->serializer->getEav();
        parent::__construct($db, $this->serializer->getRecordTable(), $this->serializer->getColumns());
    }

    /**
     * Fetches from a statement into clones of the entity prototype.
     *
     * @param Statement $statement
     * @return EntityInterface[] Keyed by ID
     */
    public function fetchAll(Statement $statement): array
    {
        return iterator_to_array($this->fetchEach($statement));
    }

    /**
     * Fetches in chunks and yields each loaded entity.
     * This is preferable over {@link fetchAll()} for iterating large result sets.
     *
     * @param Statement $statement
     * @return Generator|EntityInterface[] Keyed by ID
     */
    public function fetchEach(Statement $statement)
    {
        do {
            $entities = [];
            for ($i = 0; $i < static::EAV_BATCH_LOAD and false !== $row = $statement->fetch(); $i++) {
                $clone = clone $this->proto;
                $this->serializer->import($clone, $row);
                $entities[$row['id']] = $clone;
            }
            $this->loadEav($entities);
            yield from $entities;
        } while (!empty($entities));
    }

    /**
     * Similar to {@link loadAll()} except this can additionally search by {@link EAV} values.
     *
     * @see Predicate::match()
     *
     * @param array $match `[property => value]`
     * @param array[] $eavMatch `[eav property => attribute => value]`
     * @return Select|EntityInterface[]
     */
    public function findAll(array $match, array $eavMatch = [])
    {
        $select = $this->loadAll();
        foreach ($match as $a => $b) {
            $select->where(Predicate::match($this->db, $this[$a] ?? $a, $b));
        }
        foreach ($eavMatch as $property => $attributes) {
            $inner = $this->eav[$property]->findAll($attributes);
            $select->join($inner, $inner['entity']->isEqual($this['id']));
        }
        return $select;
    }

    /**
     * Returns an instance for the first row matching the criteria.
     *
     * @param array $match `[property => value]`
     * @param array $eavMatch `[eav property => attribute => value]`
     * @return null|EntityInterface
     */
    public function findFirst(array $match, array $eavMatch = [])
    {
        return $this->findAll($match, $eavMatch)->limit(1)->getFirst();
    }

    /**
     * @return string
     */
    final public function getClass(): string
    {
        return get_class($this->proto);
    }

    /**
     * @return EAV[]
     */
    public function getEav()
    {
        return $this->eav;
    }

    /**
     * @return EntityInterface
     */
    public function getProto()
    {
        return $this->proto;
    }

    /**
     * @return Serializer
     */
    public function getSerializer(): Serializer
    {
        return $this->serializer;
    }

    /**
     * Loads all data for a given ID (clones the prototype), or an existing instance.
     *
     * @param int|EntityInterface $id The given instance may be a subclass of the prototype.
     * @return null|EntityInterface
     */
    public function load($id)
    {
        $statement = $this->cache(__FUNCTION__, function () {
            return $this->select()->where('id = ?')->prepare();
        });
        if ($id instanceof EntityInterface) {
            assert(is_a($id, get_class($this->proto)));
            $entity = $id;
            $id = $entity->getId();
        } else {
            $entity = clone $this->proto;
        }
        $values = $statement([$id])->fetch();
        $statement->closeCursor();
        if ($values) {
            $this->serializer->import($entity, $values);
            $this->loadEav([$id => $entity]);
            return $entity;
        }
        return null;
    }

    /**
     * Returns a {@link Select} that fetches instances.
     *
     * @return Select|EntityInterface[]
     */
    public function loadAll()
    {
        return $this->select()->setFetcher(function (Statement $statement) {
            yield from $this->fetchEach($statement);
        });
    }

    /**
     * Loads and sets all EAV properties for an array of entities keyed by ID.
     *
     * @param EntityInterface[] $entities Keyed by ID
     */
    protected function loadEav(array $entities): void
    {
        $ids = array_keys($entities);
        foreach ($this->eav as $attr => $eav) {
            foreach ($eav->loadAll($ids) as $id => $values) {
                $this->serializer->setValue($entities[$id], $attr, $values);
            }
        }
    }

    /**
     * Upserts record and EAV data.
     *
     * @param EntityInterface $entity
     * @return int ID
     */
    public function save(EntityInterface $entity): int
    {
        if (!$entity->getId()) {
            $this->saveInsert($entity);
        } else {
            $this->saveUpdate($entity);
        }
        $this->saveEav($entity);
        return $entity->getId();
    }

    /**
     * @param EntityInterface $entity
     */
    protected function saveEav(EntityInterface $entity): void
    {
        $id = $entity->getId();
        foreach ($this->eav as $attr => $eav) {
            $values = $this->serializer->getValue($entity, $attr);
            // skip if null
            if (isset($values)) {
                $eav->save($id, $values);
            }
        }
    }

    /**
     * Inserts a new row and updates the entity's ID.
     *
     * @param EntityInterface $entity
     */
    protected function saveInsert(EntityInterface $entity): void
    {
        $statement = $this->cache(__FUNCTION__, function () {
            $slots = $this->db->slots(array_keys($this->columns));
            unset($slots['id']);
            $columns = implode(',', array_keys($slots));
            $slots = implode(',', $slots);
            return $this->db->prepare("INSERT INTO {$this} ({$columns}) VALUES ({$slots})");
        });
        $values = $this->serializer->export($entity);
        unset($values['id']);
        $this->serializer->setValue($entity, 'id', $statement($values)->getId());
        $statement->closeCursor();
    }

    /**
     * Updates the existing row for the entity.
     *
     * @param EntityInterface $entity
     */
    protected function saveUpdate(EntityInterface $entity): void
    {
        $statement = $this->cache(__FUNCTION__, function () {
            $slots = $this->db->slots(array_keys($this->columns));
            foreach ($slots as $column => $slot) {
                $slots[$column] = "{$column} = {$slot}";
            }
            unset($slots['id']);
            $slots = implode(', ', $slots);
            return $this->db->prepare("UPDATE {$this} SET {$slots} WHERE id = :id");
        });
        $values = $this->serializer->export($entity);
        $statement->execute($values);
        $statement->closeCursor();
    }

    /**
     * @param EntityInterface $proto
     * @return $this
     */
    public function setProto(EntityInterface $proto)
    {
        $this->proto = $proto;
        return $this;
    }

}
