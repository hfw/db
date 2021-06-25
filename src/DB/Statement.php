<?php

namespace Helix\DB;

use ArgumentCountError;
use Helix\DB;
use PDOStatement;

/**
 * Extends `PDOStatement` for fluency and logging.
 */
class Statement extends PDOStatement
{

    /**
     * @var DB
     */
    protected $db;

    /**
     * PDO requires this to be protected.
     *
     * @param DB $db
     */
    protected function __construct(DB $db)
    {
        $this->db = $db;
    }

    /**
     * Fluent execution.
     *
     * @param array $args
     * @return $this
     */
    public function __invoke(array $args = null)
    {
        $this->execute($args);
        return $this;
    }

    /**
     * The query string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->queryString;
    }

    /**
     * Logs.
     * PHP returns `false` instead of throwing if too many arguments were given.
     * This checks for that and throws.
     *
     * @param array $args
     * @return bool
     * @throws ArgumentCountError
     */
    public function execute($args = null)
    {
        $this->db->log($this->queryString);
        if ($result = !parent::execute($args)) {
            $info = $this->errorInfo();
            if ($info[0] == 0) {
                $argc = count($args);
                throw new ArgumentCountError("Too many arguments given ({$argc})");
            }
        }
        return $result;
    }

    /**
     * `lastInsertId()`
     *
     * @return int
     */
    public function getId(): int
    {
        return (int)$this->db->lastInsertId();
    }
}
