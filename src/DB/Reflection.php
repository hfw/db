<?php

namespace Helix\DB;

use Helix\DB;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Interprets classes and annotations, and manipulates objects.
 *
 * > Programmer's Note: Manipulating subclasses through a parent's reflection is allowed.
 *
 * @method static static factory(DB $db, string|object $class)
 *
 * @TODO Allow column aliasing.
 */
class Reflection
{

    use FactoryTrait;

    protected const RX_RECORD = '/\*\h*@record\h+(?<table>\w+)/i';
    protected const RX_IS_COLUMN = '/\*\h*@col(umn)?\b/i';
    protected const RX_UNIQUE = '/\*\h*@unique(\h+(?<ident>[a-z_]+))?/i';
    protected const RX_VAR = '/\*\h*@var\h+(?<type>\S+)/i'; // includes pipes and backslashes
    protected const RX_NULL = '/(\bnull\|)|(\|null\b)/i';
    protected const RX_EAV = '/\*\h*@eav\h+(?<table>\w+)/i';
    protected const RX_EAV_VAR = '/\*\h*@var\h+(?<type>\w+)\[\]/i'; // typed array
    protected const RX_JUNCTION = '/\*\h*@junction\h+(?<table>\w+)/i';
    protected const RX_FOREIGN = '/\*\h*@foreign\h+(?<column>\w+)\h+(?<class>\S+)/i';

    /**
     * Maps annotated/native scalar types to storage types acceptable for `settype()`
     *
     * @see Schema::T_CONST_NAMES keys
     */
    const SCALARS = [
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
     * @var ReflectionClass
     */
    protected $class;

    /**
     * @var string[]
     */
    protected $columns;

    /**
     * @var DB
     */
    protected $db;

    /**
     * @var array
     */
    protected $defaults = [];

    /**
     * @var ReflectionProperty[]
     */
    protected $properties = [];

    /**
     * @var string[]
     */
    protected $unique;

    /**
     * @param DB $db
     * @param string|object $class
     */
    public function __construct(DB $db, $class)
    {
        $this->db = $db;
        $this->class = new ReflectionClass($class);
        $this->defaults = $this->class->getDefaultProperties();
        foreach ($this->class->getProperties() as $property) {
            $property->setAccessible(true);
            $this->properties[$property->getName()] = $property;
        }
    }

    /**
     * @TODO allow aliasing
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        if (!isset($this->columns)) {
            $this->columns = [];
            foreach ($this->properties as $name => $property) {
                if (preg_match(static::RX_IS_COLUMN, $property->getDocComment())) {
                    $this->columns[$name] = $name;
                }
            }
        }
        return $this->columns;
    }

    /**
     * @return EAV[]
     */
    public function getEav()
    {
        $EAV = [];
        foreach ($this->properties as $name => $prop) {
            $doc = $prop->getDocComment();
            if (preg_match(static::RX_EAV, $doc, $eav)) {
                preg_match(static::RX_EAV_VAR, $doc, $var);
                $type = $var['type'] ?? 'string';
                $type = static::SCALARS[$type] ?? 'string';
                $EAV[$name] = EAV::factory($this->db, $eav['table'], $type);
            }
        }
        return $EAV;
    }

    /**
     * Classes keyed by column.
     *
     * @return string[]
     */
    public function getForeignClasses(): array
    {
        preg_match_all(static::RX_FOREIGN, $this->class->getDocComment(), $foreign, PREG_SET_ORDER);
        return array_column($foreign, 'class', 'column');
    }

    /**
     * @return string
     */
    public function getJunctionTable(): string
    {
        preg_match(static::RX_JUNCTION, $this->class->getDocComment(), $junction);
        return $junction['table'];
    }

    /**
     * @return string
     */
    public function getRecordTable(): string
    {
        preg_match(static::RX_RECORD, $this->class->getDocComment(), $record);
        return $record['table'];
    }

    /**
     * Scalar storage type, or FQN of a complex type.
     *
     * @param string $property
     * @return string
     */
    public function getType(string $property): string
    {
        return $this->getType_reflection($property)
            ?? $this->getType_var($property)
            ?? static::SCALARS[gettype($this->defaults[$property])];
    }

    /**
     * @param string $property
     * @return null|string
     */
    protected function getType_reflection(string $property): ?string
    {
        if ($type = $this->properties[$property]->getType() and $type instanceof ReflectionNamedType) {
            return static::SCALARS[$type->getName()] ?? $type->getName();
        }
        return null;
    }

    /**
     * @param string $property
     * @return null|string
     */
    protected function getType_var(string $property): ?string
    {
        if (preg_match(static::RX_VAR, $this->properties[$property]->getDocComment(), $var)) {
            $type = preg_replace(static::RX_NULL, '', $var['type']); // remove null
            if (isset(static::SCALARS[$type])) {
                return static::SCALARS[$type];
            }
            // it's beyond the scope of this class to parse "use" statements (for now),
            // @var <CLASS> must be a FQN in order to work.
            return ltrim($type, '\\');
        }
        return null;
    }

    /**
     * Column groupings for unique constraints.
     * - Column-level constraints are enumerated names.
     * - Table-level (multi-column) constraints are names grouped under an arbitrary shared identifier.
     *
     * `[ 'foo', 'my_multi'=>['bar','baz'], ... ]`
     *
     * @return array
     */
    public function getUnique()
    {
        if (!isset($this->unique)) {
            $this->unique = [];
            foreach ($this->properties as $property) {
                if (preg_match(static::RX_UNIQUE, $property->getDocComment(), $match)) {
                    if (isset($match['ident'])) {
                        $this->unique[$match['ident']][] = $property->getName();
                    } else {
                        $this->unique[] = $property->getName();
                    }
                }
            }
        }
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
        foreach ($this->getUnique() as $key => $value) {
            if (is_string($key) and in_array($property, $value)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Gets and returns a value through reflection.
     *
     * @param object $object
     * @param string $property
     * @return mixed
     */
    public function getValue(object $object, string $property)
    {
        return $this->properties[$property]->getValue($object);
    }

    /**
     * Whether a property allows `NULL` as a value.
     *
     * @param string $property
     * @return bool
     */
    public function isNullable(string $property): bool
    {
        if ($type = $this->properties[$property]->getType()) {
            return $type->allowsNull();
        }
        if (preg_match(static::RX_VAR, $this->properties[$property]->getDocComment(), $var)) {
            return (bool)preg_match(static::RX_NULL, $var['type']);
        }
        return $this->defaults[$property] === null;
    }

    /**
     * Whether a property has a unique-key constraint of its own.
     *
     * @param string $property
     * @return bool
     */
    final public function isUnique(string $property): bool
    {
        return in_array($property, $this->getUnique());
    }

    /**
     * @return object
     */
    public function newProto(): object
    {
        return $this->class->newInstanceWithoutConstructor();
    }

    /**
     * Sets a value through reflection.
     *
     * @param object $object
     * @param string $property
     * @param mixed $value
     */
    public function setValue(object $object, string $property, $value): void
    {
        $this->properties[$property]->setValue($object, $value);
    }
}
