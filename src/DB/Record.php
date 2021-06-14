<?php

namespace Helix\DB;

use Generator;
use Helix\DB;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

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
 * - `@eav <TABLE>`
 *
 * Property value types are preserved as long as they are scalar or annotated with `@var`.
 * Types may be nullable.
 *
 * > Annotating the types `String` (capital "S") or `STRING` (all caps) results in `TEXT` and `BLOB`
 *
 * @method static static factory(DB $db, EntityInterface $proto, string $table, array $columns, array $eav = [])
 *
 * @TODO Allow constraints in the `column` tag, supporting single and multi-column.
 */
class Record extends Table {

    protected const RX_RECORD = '/\*\h*@record\h+(?<table>\w+)/i';
    protected const RX_IS_COLUMN = '/\*\h*@col(umn)?\b/i';
    protected const RX_VAR = '/\*\h*@var\h+(?<type>\S+)/i'; // includes pipes
    protected const RX_NULL = '/\b\|?null\|?\b/i'; // leading or trailing only
    protected const RX_IS_SCALAR = '/^bool(ean)?|int(eger)?|float|double|string$/i';
    protected const RX_EAV = '/\*\h*@eav\h+(?<table>\w+)/i';
    protected const RX_EAV_VAR = '/\*\h*@var\h+(?<type>\w+)\[\]/i'; // typed array

    /**
     * `[property => EAV]`
     *
     * @var EAV[]
     */
    protected $eav = [];

    /**
     * `[ property => is nullable ]`
     *
     * @var bool[]
     */
    protected $nullable = [];

    /**
     * `[property => ReflectionProperty]`
     *
     * @var ReflectionProperty[]
     */
    protected $properties = [];

    /**
     * A boilerplate instance of the class, to clone and populate.
     * This defaults to a naively created instance without invoking the constructor.
     *
     * @var EntityInterface
     */
    protected $proto;

    /**
     * Scalar property types. Types may be nullable.
     *
     *  - `bool`
     *  - `float` or `double`
     *  - `int`
     *  - `string`
     *
     * `[property => type]`
     *
     * @var string[]
     */
    protected $types = [];

    /**
     * @param DB $db
     * @param string|EntityInterface $class
     * @return Record
     */
    public static function fromClass (DB $db, $class) {
        $rClass = new ReflectionClass($class);
        assert($rClass->isInstantiable());
        $columns = [];
        $EAV = [];
        foreach ($rClass->getProperties() as $rProp) {
            $doc = $rProp->getDocComment();
            if (preg_match(static::RX_IS_COLUMN, $doc)) {
                $columns[] = $rProp->getName();
            }
            elseif (preg_match(static::RX_EAV, $doc, $eav)) {
                preg_match(static::RX_EAV_VAR, $doc, $var);
                $EAV[$rProp->getName()] = EAV::factory($db, $eav['table'], $var['type'] ?? 'string');
            }
        }
        preg_match(static::RX_RECORD, $rClass->getDocComment(), $record);
        if (!is_object($class)) {
            $class = $rClass->newInstanceWithoutConstructor();
        }
        return static::factory($db, $class, $record['table'], $columns, $EAV);
    }

    /**
     * @param DB $db
     * @param EntityInterface $proto
     * @param string $table
     * @param string[] $columns Property names.
     * @param EAV[] $eav Keyed by property name.
     */
    public function __construct (DB $db, EntityInterface $proto, string $table, array $columns, array $eav = []) {
        parent::__construct($db, $table, $columns);
        $this->proto = $proto;
        $rClass = new ReflectionClass($proto);
        $defaults = $rClass->getDefaultProperties();
        foreach ($columns as $name) {
            $rProp = $rClass->getProperty($name);
            $rProp->setAccessible(true);
            $this->properties[$name] = $rProp;
            // assume nullable string
            $type = 'null|string';
            // infer the type from reflection
            if ($rType = $rProp->getType() and $rType instanceof ReflectionNamedType) {
                assert($rType->isBuiltin());
                $type = $rType->allowsNull() ? "null|{$rType->getName()}" : $rType->getName();
            }
            // infer the type from the default value
            elseif (isset($defaults[$name])) {
                $type = gettype($defaults[$name]);
            }
            // always go with @var if present
            if (preg_match(static::RX_VAR, $rProp->getDocComment(), $var)) {
                $type = $var['type'];
            }
            // extract nullable
            $type = preg_replace(static::RX_NULL, '', $type, -1, $nullable);
            $this->nullable[$name] = (bool)$nullable;
            // fall back to "string" if it's not a native scalar (e.g. "number")
            if (!preg_match(static::RX_IS_SCALAR, $type)) {
                $type = 'string';
            }
            $this->types[$name] = $type;
        }
        $this->nullable['id'] = false;
        $this->types['id'] = 'int';
        $this->eav = $eav;
        foreach (array_keys($eav) as $name) {
            $rProp = $rClass->getProperty($name);
            $rProp->setAccessible(true);
            $this->properties[$name] = $rProp;
        }
    }

    /**
     * Fetches from a statement into clones of the entity prototype.
     *
     * @param Statement $statement
     * @return EntityInterface[] Keyed by ID
     */
    public function fetchAll (Statement $statement): array {
        return iterator_to_array($this->fetchEach($statement));
    }

    /**
     * Fetches in chunks and yields each loaded entity.
     * This is preferable over {@link fetchAll()} for iterating large result sets.
     *
     * @param Statement $statement
     * @return Generator|EntityInterface[] Keyed by ID
     */
    public function fetchEach (Statement $statement) {
        do {
            $entities = [];
            for ($i = 0; $i < 256 and false !== $row = $statement->fetch(); $i++) {
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
     * @see DB::match()
     *
     * @param array $match `[property => value]`
     * @param array[] $eavMatch `[eav property => attribute => value]`
     * @return Select|EntityInterface[]
     */
    public function findAll (array $match, array $eavMatch = []) {
        $select = $this->loadAll();
        foreach ($match as $a => $b) {
            $select->where($this->db->match($this[$a] ?? $a, $b));
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
    public function findFirst (array $match, array $eavMatch = []) {
        return $this->findAll($match, $eavMatch)->limit(1)->getFirst();
    }

    /**
     * @return string
     */
    final public function getClass (): string {
        return get_class($this->proto);
    }

    /**
     * @return EAV[]
     */
    public function getEav () {
        return $this->eav;
    }

    /**
     * Enumerated property names.
     *
     * @return string[]
     */
    final public function getProperties (): array {
        return array_keys($this->properties);
    }

    /**
     * @return EntityInterface
     */
    public function getProto () {
        return $this->proto;
    }

    /**
     * Returns the native/annotated property types.
     *
     * This doesn't include whether the property is nullable. Use {@link isNullable()} for that.
     *
     * @return string[]
     */
    final public function getTypes (): array {
        return $this->types;
    }

    /**
     * @param EntityInterface $entity
     * @return array
     */
    protected function getValues (EntityInterface $entity): array {
        $values = [];
        foreach (array_keys($this->columns) as $name) {
            $values[$name] = $this->properties[$name]->getValue($entity);
        }
        return $values;
    }

    /**
     * @param string $property
     * @return bool
     */
    final public function isNullable (string $property): bool {
        return $this->nullable[$property];
    }

    /**
     * Loads all data for a given ID into a clone of the prototype.
     *
     * @param int $id
     * @return null|EntityInterface
     */
    public function load (int $id) {
        $statement = $this->cache(__FUNCTION__, function() {
            return $this->select()->where('id = ?')->prepare();
        });
        $values = $statement([$id])->fetch();
        $statement->closeCursor();
        if ($values) {
            $entity = clone $this->proto;
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
    public function loadAll () {
        return $this->select()->setFetcher(function(Statement $statement) {
            yield from $this->fetchEach($statement);
        });
    }

    /**
     * Loads and sets all EAV properties for an array of entities keyed by ID.
     *
     * @param EntityInterface[] $entities
     */
    protected function loadEav (array $entities): void {
        $ids = array_keys($entities);
        foreach ($this->eav as $name => $eav) {
            foreach ($eav->loadAll($ids) as $id => $values) {
                $this->properties[$name]->setValue($entities[$id], $values);
            }
        }
    }

    /**
     * Upserts record and EAV data.
     *
     * @param EntityInterface $entity
     * @return int ID
     */
    public function save (EntityInterface $entity): int {
        if (!$entity->getId()) {
            $this->saveInsert($entity);
        }
        else {
            $this->saveUpdate($entity);
        }
        $this->saveEav($entity);
        return $entity->getId();
    }

    /**
     * @param EntityInterface $entity
     */
    protected function saveEav (EntityInterface $entity): void {
        $id = $entity->getId();
        foreach ($this->eav as $name => $eav) {
            // may be null to skip
            $values = $this->properties[$name]->getValue($entity);
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
    protected function saveInsert (EntityInterface $entity): void {
        $statement = $this->cache(__FUNCTION__, function() {
            $slots = $this->db->slots(array_keys($this->columns));
            unset($slots['id']);
            $columns = implode(',', array_keys($slots));
            $slots = implode(',', $slots);
            return $this->db->prepare("INSERT INTO {$this} ({$columns}) VALUES ({$slots})");
        });
        $values = $this->getValues($entity);
        unset($values['id']);
        $this->properties['id']->setValue($entity, $statement($values)->getId());
        $statement->closeCursor();
    }

    /**
     * Updates the existing row for the entity.
     *
     * @param EntityInterface $entity
     */
    protected function saveUpdate (EntityInterface $entity): void {
        $statement = $this->cache(__FUNCTION__, function() {
            $slots = $this->db->slotsEqual(array_keys($this->columns));
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
    public function setProto (EntityInterface $proto) {
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
    protected function setType (string $property, $value) {
        if (isset($value)) {
            // doesn't care about the type's letter case
            settype($value, $this->types[$property]);
        }
        return $value;
    }

    /**
     * @param EntityInterface $entity
     * @param array $values
     */
    protected function setValues (EntityInterface $entity, array $values): void {
        foreach ($values as $name => $value) {
            if (isset($this->properties[$name])) {
                $this->properties[$name]->setValue($entity, $this->setType($name, $value));
            }
            else {
                // attempt to set unknown fields directly on the instance.
                $entity->{$name} = $value;
            }
        }
    }
}