<?php

namespace Helix\DB;

use Helix\DB;
use Helix\DB\SQL\AggregateTrait;
use Helix\DB\SQL\CastTrait;
use Helix\DB\SQL\ComparisonTrait;
use Helix\DB\SQL\DateTimeTrait;
use Helix\DB\SQL\NumTrait;
use Helix\DB\SQL\TextTrait;
use Helix\DB\SQL\ValueInterface;

/**
 * Immutable column expression. Can produce all available transformations.
 *
 * @immutable Mutations operate on and return clones.
 *
 * @method static static factory(DB $db, string $name, string $qualifier = '')
 */
class Column implements ValueInterface {

    use AggregateTrait;
    use CastTrait;
    use ComparisonTrait;
    use DateTimeTrait;
    use FactoryTrait;
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
    public function __construct (DB $db, string $name, string $qualifier = '') {
        $this->db = $db;
        $this->name = $name;
        $this->qualifier = $qualifier;
    }

    /**
     * Returns the qualified name.
     *
     * @return string
     */
    public function __toString () {
        if (strlen($this->qualifier)) {
            return "{$this->qualifier}.{$this->name}";
        }
        return $this->name;
    }

    /**
     * @return string
     */
    final public function getName (): string {
        return $this->name;
    }

    /**
     * @return string
     */
    final public function getQualifier (): string {
        return $this->qualifier;
    }

    /**
     * Returns a {@link Select} for the column's values. The column must be qualified.
     *
     * @return Select|scalar[]
     */
    public function select () {
        return Select::factory($this->db, $this->qualifier, [$this->name])
            ->setFetcher(function(Statement $statement) {
                while (false !== $value = $statement->fetchColumn()) {
                    yield $value;
                }
            });
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName (string $name) {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    /**
     * @param string $qualifier
     * @return $this
     */
    public function setQualifier (string $qualifier) {
        $clone = clone $this;
        $clone->qualifier = $qualifier;
        return $clone;
    }
}