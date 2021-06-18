<?php

namespace Helix\DB;

use ArrayAccess;
use Helix\DB;
use Helix\DB\Fluent\DateTime\DateTimeTrait;
use Helix\DB\Fluent\Num\NumTrait;
use Helix\DB\Fluent\Text\TextTrait;
use Helix\DB\Fluent\ValueInterface;
use LogicException;

/**
 * Immutable column expression. Can be treated as any data type.
 *
 * Read-only array access is provided for easily retrieving aggregate function results.
 *
 * @immutable Mutations operate on and return clones.
 *
 * @method static static factory(DB $db, string $name, string $qualifier = '')
 */
class Column implements ArrayAccess, ValueInterface
{

    use FactoryTrait;
    use DateTimeTrait;
    use NumTrait;
    use TextTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $qualifier;

    /**
     * @param DB $db
     * @param string $name
     * @param string $qualifier
     */
    public function __construct(DB $db, string $name, string $qualifier = '')
    {
        $this->db = $db;
        $this->name = $name;
        $this->qualifier = $qualifier;
    }

    /**
     * Returns the qualified name.
     *
     * @return string
     */
    public function __toString()
    {
        if (strlen($this->qualifier)) {
            return "{$this->qualifier}.{$this->name}";
        }
        return $this->name;
    }

    /**
     * @return string
     */
    final public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    final public function getQualifier(): string
    {
        return $this->qualifier;
    }

    /**
     * Aggregate function results are always available.
     *
     * @param mixed $value
     * @return true
     */
    final public function offsetExists($value)
    {
        return true;
    }

    /**
     * Returns the result of an aggregate function run over the column expression.
     *
     * Example: `min` returns the result of `SELECT MIN($this) FROM $this->qualifier`
     *
     * @see DB\Fluent\Value\AggregateTrait
     * @param string $aggregator
     * @return null|string
     */
    public function offsetGet($aggregator)
    {
        $aggregator = preg_replace('/[ _()]/', '', $aggregator); // accept a variety of forms
        $aggregator = $this->{$aggregator}(); // methods are not case sensitive
        return Select::factory($this->db, $this->qualifier, [$aggregator])->getResult();
    }

    /**
     * Throws.
     *
     * @param mixed $offset
     * @param mixed $value
     * @throws LogicException
     */
    final public function offsetSet($offset, $value)
    {
        throw new LogicException("Column aggregation is read-only");
    }

    /**
     * Throws.
     *
     * @param mixed $offset
     * @throws LogicException
     */
    final public function offsetUnset($offset)
    {
        throw new LogicException("Column aggregation is read-only");
    }

    /**
     * Returns a {@link Select} for the column's values. The column must be qualified.
     *
     * @return Select|scalar[]
     */
    public function select()
    {
        return Select::factory($this->db, $this->qualifier, [$this->name])
            ->setFetcher(function (Statement $statement) {
                while (false !== $value = $statement->fetchColumn()) {
                    yield $value;
                }
            });
    }

    /**
     * Returns an aliased clone.
     *
     * If you want to rename the column in the schema, use {@link Schema::renameColumn()}
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name)
    {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    /**
     * @param string $qualifier
     * @return $this
     */
    public function setQualifier(string $qualifier)
    {
        $clone = clone $this;
        $clone->qualifier = $qualifier;
        return $clone;
    }
}
