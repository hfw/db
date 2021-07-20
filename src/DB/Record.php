<?php

namespace Helix\DB;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Helix\DB;
use Helix\DB\Fluent\Predicate;
use stdClass;

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
     * Maps complex types to storage types.
     *
     * {@link EntityInterface} is always dehydrated as the integer ID.
     *
     * @see Record::getValues_dehydrate()
     * @see Record::setType_hydrate()
     * @see Schema::T_CONST_NAMES
     */
    protected const DEHYDRATE_AS = [
        'array' => 'STRING', // blob. eav is better than this for 1D arrays.
        'object' => 'STRING', // blob.
        stdClass::class => 'STRING', // blob
        DateTime::class => 'DateTime',
        DateTimeImmutable::class => 'DateTime',
    ];

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
     * The specific classes used to hydrate classed properties, like `DateTime`.
     *
     * `[ property => class ]`
     *
     * @var string[]
     */
    protected $hydration = [];

    /**
     * `[ property => is nullable ]`
     *
     * @var bool[]
     */
    protected $nullable = [];

    /**
     * A boilerplate instance of the class, to clone and populate.
     *
     * @var EntityInterface
     */
    protected $proto;

    /**
     * @var Reflection
     */
    protected $ref;

    /**
     * Storage types.
     *
     * `[property => type]`
     *
     * @var string[]
     */
    protected $types = [];

    /**
     * @var array
     */
    protected $unique;

    /**
     * @var DateTimeZone
     */
    protected DateTimeZone $utc;

    /**
     * @param DB $db
     * @param string|EntityInterface $class
     */
    public function __construct(DB $db, $class)
    {
        $this->ref = Reflection::factory($db, $class);
        $this->proto = is_object($class) ? $class : $this->ref->newProto();
        assert($this->proto instanceof EntityInterface);
        $this->unique = $this->ref->getUnique();
        $this->utc = new DateTimeZone('UTC');

        // TODO allow aliasing
        $cols = $this->ref->getColumns();
        foreach ($cols as $col) {
            $type = $this->ref->getType($col);
            if (isset(static::DEHYDRATE_AS[$type])) {
                $this->hydration[$col] = $type;
                $type = static::DEHYDRATE_AS[$type];
            } elseif (is_a($type, EntityInterface::class, true)) {
                $this->hydration[$col] = $type;
                $type = 'int';
            }
            $this->types[$col] = $type;
            $this->nullable[$col] = $this->ref->isNullable($col);
        }
        $this->types['id'] = 'int';
        $this->nullable['id'] = false;
        $this->eav = $this->ref->getEav();

        parent::__construct($db, $this->ref->getRecordTable(), $cols);
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
                $this->setValues($clone, $row);
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
     * Returns a native/annotated property type.
     *
     * This doesn't include whether the property is nullable. Use {@link Record::isNullable()} for that.
     *
     * @param string $property
     * @return string
     */
    final public function getType(string $property): string
    {
        return $this->types[$property];
    }

    /**
     * Returns the native/annotated property types.
     *
     * This doesn't include whether the properties are nullable. Use {@link Record::isNullable()} for that.
     *
     * @return string[]
     */
    final public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @return array
     */
    final public function getUnique(): array
    {
        return $this->unique;
    }

    /**
     * The shared identifier if a property is part of a multi-column unique-key.
     *
     * @param string $property
     * @return null|string The shared identifier, or nothing.
     */
    final public function getUniqueGroup(string $property): ?string
    {
        foreach ($this->unique as $key => $value) {
            if (is_string($key) and in_array($property, $value)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * @param EntityInterface $entity
     * @return array
     */
    protected function getValues(EntityInterface $entity): array
    {
        $values = [];
        foreach (array_keys($this->columns) as $prop) {
            $value = $this->ref->getValue($entity, $prop);
            if (isset($value, $this->hydration[$prop])) {
                $from = $this->hydration[$prop];
                $to = static::DEHYDRATE_AS[$from];
                $value = $this->getValues_dehydrate($to, $from, $value);
            }
            $values[$prop] = $value;
        }
        return $values;
    }

    /**
     * Dehydrates a complex property's value for storage in a scalar column.
     *
     * @see Record::setType_hydrate() inverse
     *
     * @param string $to The storage type.
     * @param string $from The strict type from the class definition.
     * @param array|object $hydrated
     * @return null|scalar
     */
    protected function getValues_dehydrate(string $to, string $from, $hydrated)
    {
        // we don't need $from here but it's given for posterity
        unset($from);

        // dehydrate entities to their id
        if ($hydrated instanceof EntityInterface) {
            return $hydrated->getId();
        }

        // dehydrate DateTime
        if ($to === 'DateTime') {
            assert($hydrated instanceof DateTime or $hydrated instanceof DateTimeImmutable);
            return (clone $hydrated)->setTimezone($this->utc)->format(Schema::DATETIME_FORMAT);
        }

        // dehydrate other complex types
        return serialize($hydrated);
    }

    /**
     * @param string $property
     * @return bool
     */
    final public function isNullable(string $property): bool
    {
        return $this->nullable[$property];
    }

    /**
     * Whether a property has a unique-key constraint of its own.
     *
     * @param string $property
     * @return bool
     */
    final public function isUnique(string $property): bool
    {
        return in_array($property, $this->unique);
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
            $this->setValues($entity, $values);
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
                $this->ref->setValue($entities[$id], $attr, $values);
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
            $values = $this->ref->getValue($entity, $attr);
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
        $values = $this->getValues($entity);
        unset($values['id']);
        $this->ref->setValue($entity, 'id', $statement($values)->getId());
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
        $statement->execute($this->getValues($entity));
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

    /**
     * Converts a value from storage into the native/annotated type.
     *
     * @param string $property
     * @param mixed $value
     * @return mixed
     */
    protected function setType(string $property, $value)
    {
        if (isset($value)) {
            // complex?
            if (isset($this->hydration[$property])) {
                $to = $this->hydration[$property];
                $from = static::DEHYDRATE_AS[$to];
                return $this->setType_hydrate($to, $from, $value);
            }
            // scalar. this function doesn't care about the type's letter case.
            settype($value, $this->types[$property]);
        }
        return $value;
    }

    /**
     * Hydrates a complex value from scalar storage.
     *
     * @see Record::getValues_dehydrate() inverse
     *
     * @param string $to The strict type from the class definition.
     * @param string $from The storage type.
     * @param scalar $dehydrated
     * @return array|object
     */
    protected function setType_hydrate(string $to, string $from, $dehydrated)
    {
        // hydrate entities from their id
        if (is_a($to, EntityInterface::class, true)) {
            return $this->db->getRecord($to)->load($dehydrated);
        }

        // hydrate DateTime
        if ($from === 'DateTime') {
            return new $to($dehydrated, $this->utc);
        }

        // hydrate other complex types
        $complex = unserialize($dehydrated);
        assert(is_array($complex) or is_object($complex));
        return $complex;
    }

    /**
     * @param EntityInterface $entity
     * @param array $values
     */
    protected function setValues(EntityInterface $entity, array $values): void
    {
        foreach ($values as $prop => $value) {
            if (isset($this->columns[$prop])) {
                $value = $this->setType($prop, $value);
                $this->ref->setValue($entity, $prop, $value);
            } else {
                // attempt to set unknown fields directly on the instance.
                $entity->{$prop} = $value;
            }
        }
    }
}
