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
 * Property value types are preserved as long as they are annotated with `@var`.
 */
class Record extends Table {

    /**
     * `[property => EAV]`
     *
     * @var EAV[]
     */
    protected $eav = [];

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
     * Scalar property types.
     *
     *  - bool
     *  - float (or double)
     *  - int
     *  - string
     *
     * `[property => type]`
     *
     * @var array
     */
    protected $types = [];

    /**
     * @param DB $db
     * @param string|EntityInterface $class
     * @return Record
     */
    public static function fromClass (DB $db, $class) {
        return (function() use ($db, $class) {
            $rClass = new ReflectionClass($class);
            $columns = [];
            /** @var EAV[] $eav */
            $eav = [];
            foreach ($rClass->getProperties() as $rProp) {
                if (preg_match('/@col(umn)?[\s$]/', $rProp->getDocComment())) {
                    $columns[] = $rProp->getName();
                }
                elseif (preg_match('/@eav\s+(?<table>\S+)/', $rProp->getDocComment(), $attr)) {
                    $eav[$rProp->getName()] = $db->factory(EAV::class, $db, $attr['table']);
                }
            }
            preg_match('/@record\s+(?<table>\S+)/', $rClass->getDocComment(), $record);
            if (!is_object($class)) {
                $class = $rClass->newInstanceWithoutConstructor();
            }
            return new static($db, $class, $record['table'], $columns, $eav);
        })();
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
        (function() use ($proto, $columns, $eav) {
            $rClass = new ReflectionClass($proto);
            $defaults = $rClass->getDefaultProperties();
            foreach ($columns as $name) {
                $rProp = $rClass->getProperty($name);
                $rProp->setAccessible(true);
                $this->properties[$name] = $rProp;
                // infer the type from the default value
                $type = gettype($defaults[$name] ?? 'string');
                // check for explicit type via annotation
                if (preg_match('/@var\s+(?<type>[a-z]+)[\s$]/', $rProp->getDocComment(), $var)) {
                    $type = $var['type'];
                }
                $this->types[$name] = $type;
            }
            $this->types['id'] = 'int';
            $this->eav = $eav;
            foreach (array_keys($eav) as $name) {
                $rProp = $rClass->getProperty($name);
                $rProp->setAccessible(true);
                $this->properties[$name] = $rProp;
            }
        })();
    }

    /**
     * Returns a {@link Select}.
     *
     * @see DB::match()
     *
     * @param array $match `[property => mixed]`
     * @param array[] $eavMatch `[eav property => attribute => mixed]`
     * @return Select
     */
    public function find (array $match, array $eavMatch = []) {
        $select = $this->select();
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
     * @return EntityInterface
     */
    public function getProto () {
        return $this->proto;
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
     * Loads all data for a given ID into a clone of the prototype.
     *
     * @param int $id
     * @return null|EntityInterface
     */
    public function load (int $id) {
        $load = $this->cache(__FUNCTION__, function() {
            return $this->select(array_keys($this->columns))->where('id = ?')->prepare();
        });
        if ($values = $load([$id])->fetch()) {
            $entity = clone $this->proto;
            $this->setValues($entity, $values);
            $this->loadEav([$id => $entity]);
            return $entity;
        }
        return null;
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
        $insert = $this->cache(__FUNCTION__, function() {
            $slots = SQL::slots(array_keys($this->columns));
            unset($slots['id']);
            $columns = implode(',', array_keys($slots));
            $slots = implode(',', $slots);
            return $this->db->prepare("INSERT INTO {$this} ({$columns}) VALUES ({$slots})");
        });
        $values = $this->getValues($entity);
        unset($values['id']);
        $this->properties['id']->setValue($entity, $insert($values)->getId());
    }

    /**
     * Updates the existing row for the entity.
     *
     * @param EntityInterface $entity
     */
    protected function saveUpdate (EntityInterface $entity): void {
        $this->cache(__FUNCTION__, function() {
            $slots = SQL::slotsEqual(array_keys($this->columns));
            unset($slots['id']);
            $slots = implode(', ', $slots);
            return $this->db->prepare("UPDATE {$this} SET {$slots} WHERE id = :id");
        })->execute($this->getValues($entity));
    }

    /**
     * Sets the fetcher if the default columns are used.
     *
     * @param array $columns Defaults to all columns.
     * @return Select
     */
    public function select (array $columns = []) {
        $select = parent::select($columns);
        if (empty($columns)) {
            $select->setFetcher(function(Statement $statement) {
                yield from $this->getEach($statement);
            });
        }
        return $select;
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
            settype($value, $this->types[$name]);
            $this->properties[$name]->setValue($entity, $value);
        }
    }
}