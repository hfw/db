<?php

namespace Helix\DB;

use Helix\DB;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Interprets classes and annotations.
 *
 * > Programmer's Note: Manipulating subclasses through a parent's reflection is allowed.
 *
 * @method static static factory(DB $db, string|object $class)
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
    protected $props = [];

    /**
     * @param DB $db
     * @param string|object $class
     */
    public function __construct(DB $db, $class)
    {
        $this->db = $db;
        $this->class = new ReflectionClass($class);
        $this->defaults = $this->class->getDefaultProperties();
        foreach ($this->class->getProperties() as $prop) {
            $prop->setAccessible(true);
            $this->props[$prop->getName()] = $prop;
        }
    }

    /**
     * @TODO allow aliasing
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        $cols = [];
        foreach ($this->props as $name => $prop) {
            if (preg_match(static::RX_IS_COLUMN, $prop->getDocComment())) {
                $cols[$name] = $name;
            }
        }
        return $cols;
    }

    /**
     * @return EAV[]
     */
    public function getEav()
    {
        $EAV = [];
        foreach ($this->props as $name => $prop) {
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
     * @param string $column
     * @return string
     */
    public function getType(string $column): string
    {
        return $this->getType_reflection($column)
            ?? $this->getType_var($column)
            ?? static::SCALARS[gettype($this->defaults[$column])];
    }

    /**
     * @param string $column
     * @return null|string
     */
    protected function getType_reflection(string $column): ?string
    {
        if ($type = $this->props[$column]->getType() and $type instanceof ReflectionNamedType) {
            return static::SCALARS[$type->getName()] ?? $type->getName();
        }
        return null;
    }

    /**
     * @param string $column
     * @return null|string
     */
    protected function getType_var(string $column): ?string
    {
        if (preg_match(static::RX_VAR, $this->props[$column]->getDocComment(), $var)) {
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
        $unique = [];
        foreach ($this->props as $prop) {
            if (preg_match(static::RX_UNIQUE, $prop->getDocComment(), $match)) {
                if (isset($match['ident'])) {
                    $unique[$match['ident']][] = $prop->getName();
                } else {
                    $unique[] = $prop->getName();
                }
            }
        }
        return $unique;
    }

    /**
     * @param object $object
     * @param string $prop
     * @return mixed
     */
    public function getValue(object $object, string $prop)
    {
        return $this->props[$prop]->getValue($object);
    }

    /**
     * @param string $column
     * @return bool
     */
    public function isNullable(string $column): bool
    {
        if ($type = $this->props[$column]->getType()) {
            return $type->allowsNull();
        }
        if (preg_match(static::RX_VAR, $this->props[$column]->getDocComment(), $var)) {
            return (bool)preg_match(static::RX_NULL, $var['type']);
        }
        return $this->defaults[$column] === null;
    }

    /**
     * @return object
     */
    public function newProto(): object
    {
        return $this->class->newInstanceWithoutConstructor();
    }

    /**
     * @param object $object
     * @param string $prop
     * @param mixed $value
     */
    public function setValue(object $object, string $prop, $value)
    {
        $this->props[$prop]->setValue($object, $value);
    }
}
