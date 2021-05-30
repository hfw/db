<?php

namespace Helix\DB;

use Helix\DB;
use Helix\DB\SQL\AggregateTrait;
use Helix\DB\SQL\ComparisonTrait;
use Helix\DB\SQL\DateTimeTrait;
use Helix\DB\SQL\NumericTrait;
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

    use FactoryTrait;
    use AggregateTrait;
    use ComparisonTrait;
    use DateTimeTrait;
    use NumericTrait;
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
    public function __toString (): string {
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