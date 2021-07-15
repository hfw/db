<?php

namespace Helix\DB;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Helix\DB;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
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
 * @method static static factory(DB $db, EntityInterface $proto, string $table, array $properties, array $unique, array $eav = [])
 *
 * @TODO Auto-map singular foreign entity columns.
 */
class Record extends Table
{

    protected const RX_RECORD = '/\*\h*@record\h+(?<table>\w+)/i';
    protected const RX_IS_COLUMN = '/\*\h*@col(umn)?\b/i';
    protected const RX_UNIQUE = '/\*\h*@unique(\h+(?<ident>[a-z_]+))?/i';
    protected const RX_VAR = '/\*\h*@var\h+(?<type>\S+)/i'; // includes pipes and backslashes
    protected const RX_NULL = '/(\bnull\|)|(\|null\b)/i';
    protected const RX_EAV = '/\*\h*@eav\h+(?<table>\w+)/i';
    protected const RX_EAV_VAR = '/\*\h*@var\h+(?<type>\w+)\[\]/i'; // typed array

    /**
     * Maps complex types to storage types.
     *
     * @see Schema::T_CONST_NAMES keys
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
     * Maps annotated/native scalar types to storage types acceptable for `settype()`
     *
     * @see Schema::T_CONST_NAMES keys
     */
    protected const SCALARS = [
        'bool' => 'bool',
        'boolean' => 'bool',    // gettype()
        'double' => 'float',    // gettype()
        'false' => 'bool',      // @var
        'float' => 'float',
        'int' => 'int',
        'integer' => 'int',     // gettype()
        'NULL' => 'string',     // gettype()
        'number' => 'string',   // @var
        'scalar' => 'string',   // @var
        'string' => 'string',
        'String' => 'String',   // @var
        'STRING' => 'STRING',   // @var
        'true' => 'bool',       // @var
    ];

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
     * `[property => ReflectionProperty]`
     *
     * > Programmer's Note: Manipulating subclasses through a parent's reflection is allowed.
     *
     * @var ReflectionProperty[]
     */
    protected $properties = [];

    /**
     * A boilerplate instance of the class, to clone and populate.
     *
     * @var EntityInterface
     */
    protected $proto;

    /**
     * Storage types.
     *
     * `[property => type]`
     *
     * @var string[]
     */
    protected $types = [];

    /**
     * Column groupings for unique constraints.
     * - Column-level constraints are enumerated names.
     * - Table-level (multi-column) constraints are names grouped under an arbitrary shared identifier.
     *
     * `[ 'foo', 'my_multi'=>['bar','baz'], ... ]`
     *
     * @var array
     */
    protected $unique;

    /**
     * @var DateTimeZone
     */
    protected DateTimeZone $utc;

    /**
     * Constructs record-access from an annotated class.
     *
     * If a prototype isn't given for `$class`, this defaults to creating an instance
     * via reflection (without invoking the constructor).
     *
     * @param DB $db
     * @param string|EntityInterface $class Class or prototype instance.
     * @return Record
     */
    public static function fromClass(DB $db, $class)
    {
        $rClass = new ReflectionClass($class);
        assert($rClass->implementsInterface(EntityInterface::class));
        $properties = [];
        $unique = [];
        $eav = [];
        foreach ($rClass->getProperties() as $rProp) {
            $doc = $rProp->getDocComment();
            if (preg_match(static::RX_IS_COLUMN, $doc)) {
                $properties[] = $rProp->getName();
                if (preg_match(static::RX_UNIQUE, $doc, $rx)) {
                    if (isset($rx['ident'])) {
                        $unique[$rx['ident']][] = $rProp->getName();
                    } else {
                        $unique[] = $rProp->getName();
                    }
                }
            } elseif (preg_match(static::RX_EAV, $doc, $rx)) {
                preg_match(static::RX_EAV_VAR, $doc, $var);
                $type = $var['type'] ?? 'string';
                $type = static::SCALARS[$type] ?? 'string';
                $eav[$rProp->getName()] = EAV::factory($db, $rx['table'], $type);
            }
        }
        preg_match(static::RX_RECORD, $rClass->getDocComment(), $record);
        if (!is_object($class)) {
            assert($rClass->isInstantiable());
            $class = $rClass->newInstanceWithoutConstructor();
        }
        return static::factory($db, $class, $record['table'], $properties, $unique, $eav);
    }

    /**
     * @param DB $db
     * @param EntityInterface $proto
     * @param string $table
     * @param string[] $properties Property names.
     * @param string[] $unique Enumerated property names, or groups of property names keyed by a shared identifier.
     * @param EAV[] $eav Keyed by property name.
     */
    public function __construct(
        DB $db,
        EntityInterface $proto,
        string $table,
        array $properties,
        array $unique = [],
        array $eav = []
    ) {
        parent::__construct($db, $table, $properties);
        $this->proto = $proto;
        $this->unique = $unique;
        $this->utc = new DateTimeZone('UTC');
        $rClass = new ReflectionClass($proto);
        $defaults = $rClass->getDefaultProperties();
        foreach ($properties as $prop) {
            $rProp = $rClass->getProperty($prop);
            $type = $this->__construct_getType($prop, $rProp)
                ?? static::SCALARS[gettype($defaults[$prop])];
            $nullable = $this->__construct_isNullable($prop, $rProp)
                ?? !isset($defaults[$prop]);
            assert(isset($type, $nullable));
            $rProp->setAccessible(true);
            $this->properties[$prop] = $rProp;
            $this->types[$prop] = $type;
            $this->nullable[$prop] = $nullable;
        }
        $this->types['id'] = 'int';
        $this->nullable['id'] = false;
        $this->eav = $eav;
        foreach (array_keys($eav) as $name) {
            $rProp = $rClass->getProperty($name);
            $rProp->setAccessible(true);
            $this->properties[$name] = $rProp;
        }
    }

    /**
     * Resolves a property's storage type during {@link Record::__construct()}
     *
     * Returns `null` for unknown. The constructor will fall back to checking the class default.
     *
     * @param string $prop
     * @param ReflectionProperty $rProp
     * @return null|string Storage type, or `null` for unknown.
     */
    protected function __construct_getType(string $prop, ReflectionProperty $rProp): ?string
    {
        return $this->__construct_getType_fromReflection($prop, $rProp)
            ?? $this->__construct_getType_fromVar($prop, $rProp);
    }

    /**
     * This also sets {@link Record::$hydration} for complex types.
     *
     * @param string $prop
     * @param ReflectionProperty $rProp
     * @return null|string
     */
    protected function __construct_getType_fromReflection(string $prop, ReflectionProperty $rProp): ?string
    {
        if ($rType = $rProp->getType() and $rType instanceof ReflectionNamedType) {
            $type = $rType->getName();
            if (isset(static::SCALARS[$type])) {
                return static::SCALARS[$type];
            }
            assert(isset(static::DEHYDRATE_AS[$type]));
            $this->hydration[$prop] = $type;
            return static::DEHYDRATE_AS[$type];
        }
        return null;
    }

    /**
     * This also sets {@link Record::$hydration} for complex types ONLY IF `@var` uses a FQN.
     *
     * @param string $prop
     * @param ReflectionProperty $rProp
     * @return null|string
     */
    protected function __construct_getType_fromVar(string $prop, ReflectionProperty $rProp): ?string
    {
        if (preg_match(static::RX_VAR, $rProp->getDocComment(), $var)) {
            $type = preg_replace(static::RX_NULL, '', $var['type']); // remove null
            if (isset(static::SCALARS[$type])) {
                return static::SCALARS[$type];
            }
            // it's beyond the scope of this class to parse "use" statements,
            // @var <CLASS> must be a FQN in order to work.
            $type = ltrim($type, '\\');
            if (isset(static::DEHYDRATE_AS[$type])) {
                $this->hydration[$prop] = $type;
                return static::DEHYDRATE_AS[$type];
            }
        }
        return null;
    }

    /**
     * Resolves a property's nullability during {@link Record::__construct()}
     *
     * Returns `null` for unknown. The constructor will fall back to checking the class default.
     *
     * @param string $prop
     * @param ReflectionProperty $rProp
     * @return null|bool
     */
    protected function __construct_isNullable(string $prop, ReflectionProperty $rProp): ?bool
    {
        return $this->__construct_isNullable_fromReflection($prop, $rProp)
            ?? $this->__construct_isNullable_fromVar($prop, $rProp);
    }

    /**
     * @param string $prop
     * @param ReflectionProperty $rProp
     * @return null|bool
     */
    protected function __construct_isNullable_fromReflection(string $prop, ReflectionProperty $rProp): ?bool
    {
        if ($rType = $rProp->getType()) {
            return $rType->allowsNull();
        }
        return null;
    }

    /**
     * @param string $prop
     * @param ReflectionProperty $rProp
     * @return null|bool
     */
    protected function __construct_isNullable_fromVar(string $prop, ReflectionProperty $rProp): ?bool
    {
        if (preg_match(static::RX_VAR, $rProp->getDocComment(), $var)) {
            preg_replace(static::RX_NULL, '', $var['type'], -1, $nullable);
            return (bool)$nullable;
        }
        return null;
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
     * @see DB::match()
     *
     * @param array $match `[property => value]`
     * @param array[] $eavMatch `[eav property => attribute => value]`
     * @return Select|EntityInterface[]
     */
    public function findAll(array $match, array $eavMatch = [])
    {
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
     * Enumerated property names.
     *
     * @return string[]
     */
    final public function getProperties(): array
    {
        return array_keys($this->properties);
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
        foreach (array_keys($this->columns) as $name) {
            $value = $this->properties[$name]->getValue($entity);
            if (isset($value, $this->hydration[$name])) {
                $from = $this->hydration[$name];
                $to = static::DEHYDRATE_AS[$from];
                $value = $this->getValues_dehydrate($to, $from, $value);
            }
            $values[$name] = $value;
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
     * @return scalar
     */
    protected function getValues_dehydrate(string $to, string $from, $hydrated)
    {
        unset($from); // we don't need it here but it's given for posterity
        switch ($to) {
            case 'DateTime':
                assert($hydrated instanceof DateTime or $hydrated instanceof DateTimeImmutable);
                return (clone $hydrated)->setTimezone($this->utc)->format(Schema::DATETIME_FORMAT);
            default:
                return serialize($hydrated);
        }
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
        $this->properties['id']->setValue($entity, $statement($values)->getId());
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
        switch ($from) {
            case 'DateTime':
                /**
                 * $to might be "DateTime", "DateTimeImmutable", or an extension.
                 *
                 * @see DateTime::createFromFormat()
                 */
                return call_user_func(
                    [$to, 'createFromFormat'],
                    'Y-m-d H:i:s',
                    $dehydrated,
                    $this->utc
                );
            default:
                return unserialize($dehydrated);
        }
    }

    /**
     * @param EntityInterface $entity
     * @param array $values
     */
    protected function setValues(EntityInterface $entity, array $values): void
    {
        foreach ($values as $name => $value) {
            if (isset($this->properties[$name])) {
                $this->properties[$name]->setValue($entity, $this->setType($name, $value));
            } else {
                // attempt to set unknown fields directly on the instance.
                $entity->{$name} = $value;
            }
        }
    }
}
