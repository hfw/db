<?php

namespace Helix\DB;

use Generator;
use Helix\DB;
use ReflectionClass;
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
 * - `@eav TABLE`
 *
 * Property value types are preserved as long as they are scalar and annotated with `@var`.
 * Types may be nullable.
 *
 * > Note: Annotating the types `String` (capital "S") or `STRING` (all caps) results in `TEXT` and `BLOB`
 * > respectfully during {@link Schema::createRecordTable()}
 *
 * @method static static factory(DB $db, EntityInterface $proto, string $table, array $columns, array $eav = [])
 */
class Record extends Table {

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
        /** @var EAV[] $eav */
        $eav = [];
        foreach ($rClass->getProperties() as $rProp) {
            if (preg_match('/@col(umn)?[\s$]/', $rProp->getDocComment())) {
                $columns[] = $rProp->getName();
            }
            elseif (preg_match('/@eav\s+(?<table>\S+)/', $rProp->getDocComment(), $attr)) {
                $eav[$rProp->getName()] = EAV::factory($db, $attr['table']);
            }
        }
        preg_match('/@record\s+(?<table>\S+)/', $rClass->getDocComment(), $record);
        if (!is_object($class)) {
            $class = $rClass->newInstanceWithoutConstructor();
        }
        return static::factory($db, $class, $record['table'], $columns, $eav);
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
            // infer the type from the default value
            if (isset($defaults[$name])) {
                $type = gettype($defaults[$name]);
            }
            // check for explicit type annotation
            if (preg_match('/\*\h*@var\h+(?<type>\S+)/', $rProp->getDocComment(), $var)) {
                $type = $var['type'];
            }
            // convert "boolean" to "bool"
            $type = preg_replace('/\bboolean\b/i', 'bool', $type);
            // convert "integer" to "int"
            $type = preg_replace('/\binteger\b/i', 'int', $type);
            // convert "number" to "string"
            $type = preg_replace('/\bnumber|numeric\b/i', 'string', $type);
            // extract nullable
            $type = preg_replace('/\bnull\b/i', '', $type, -1, $nullable);
            $this->nullable[$name] = (bool)$nullable;
            // fall back to "string" if it's not scalar
            if (!preg_match('/^bool|int|float|double|string$/i', $type)) {
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
     * Returns a {@link Select} that fetches instances.
     *
     * @see DB::match()
     *
     * @param array $match `[property => mixed]`
     * @param array[] $eavMatch `[eav property => attribute => mixed]`
     * @return Select|EntityInterface[]
     */
    public function find (array $match, array $eavMatch = []) {
        $select = $this->loadAll();
        foreach ($match as $a => $b) {
            $select->where($this->db->match($this[$a] ?? $a, $b));
        }
        foreach ($eavMatch as $property => $attributes) {
            $inner = $this->getEav($property)->find($attributes);
            $select->join($inner, $inner['entity']->isEqual($this['id']));
        }
        return $select;
    }

    /**
     * Fetches from a statement into clones of the entity prototype.
     *
     * @param Statement $statement
     * @return EntityInterface[] Keyed by ID
     */
    public function getAll (Statement $statement): array {
        return iterator_to_array($this->getEach($statement));
    }

    /**
     * @return string
     */
    final public function getClass (): string {
        return get_class($this->proto);
    }

    /**
     * Fetches in chunks and yields each loaded entity.
     * This is preferable over {@link getAll()} for iterating large result sets.
     *
     * @param Statement $statement
     * @return Generator|EntityInterface[] Keyed by ID
     */
    public function getEach (Statement $statement) {
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
     * @param string $property
     * @return EAV
     */
    final public function getEav (string $property) {
        return $this->eav[$property];
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
            yield from $this->getEach($statement);
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
     * @param EntityInterface $entity
     * @param array $values
     */
    protected function setValues (EntityInterface $entity, array $values): void {
        foreach ($values as $name => $value) {
            if (isset($this->properties[$name])) {
                settype($value, $this->types[$name]); // doesn't care about letter case
                $this->properties[$name]->setValue($entity, $value);
            }
            else {
                $entity->{$name} = $value;
            }
        }
    }
}