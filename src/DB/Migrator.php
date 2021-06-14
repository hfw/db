<?php

namespace Helix\DB;

use Helix\DB;

/**
 * Migrates.
 *
 * @method static static factory(DB $db, string $dir);
 *
 * @see MigrationInterface
 */
class Migrator {

    use FactoryTrait;

    /**
     * @var DB
     */
    protected $db;

    /**
     * @var string
     */
    protected $dir;

    /**
     * @var Table
     */
    protected $table;

    /**
     * @param DB $db
     * @param string $dir
     */
    public function __construct (DB $db, string $dir) {
        $this->db = $db;
        $this->dir = $dir;
        $this->table ??= $db['__migrations__'] ?? $db->getSchema()->createTable('__migrations__', [
                'sequence' => Schema::T_STRING_STRICT | Schema::I_PRIMARY
            ])['__migrations__'];
    }

    /**
     * Migrates down within a transaction.
     *
     * @param string $to Migration sequence identifier, or `null` to step down once.
     * @return null|string The resulting current sequence identifier.
     */
    public function down (string $to = null): ?string {
        return $this->db->transact(function() use ($to) {
            $current = $this->getCurrent();
            // walk newest to oldest
            foreach (array_reverse($this->glob(), true) as $sequence => $file) {
                if ($current and $to === $current) {
                    break;
                }
                if ($current < $sequence) {
                    continue;
                }
                $this->db->transact(fn() => $this->getMigration($file)->down($this->db->getSchema()));
                $this->table->delete(['sequence' => $sequence]);
                $current = $this->getCurrent();
                if ($to === null) {
                    break;
                }
            }
            return $current;
        });
    }

    /**
     * Returns the sequence identifier of the most recent upgrade.
     *
     * @return null|string
     */
    public function getCurrent (): ?string {
        return $this->table->select([$this->table['sequence']->max()])->getResult();
    }

    /**
     * @param array $spec
     * @return MigrationInterface
     */
    protected function getMigration (string $file) {
        $migration = include "{$file}";
        assert($migration instanceof MigrationInterface);
        return $migration;
    }

    /**
     * Scans the migration directory for `<SEQUENCE>.php` files.
     *
     * @return string[] [ sequence => file ]
     */
    protected function glob () {
        $files = [];
        foreach (glob("{$this->dir}/*.php") as $file) {
            $files[basename($file, '.php')] = $file;
        }
        return $files;
    }

    /**
     * Migrates up within a transaction.
     *
     * @param null|string $to Migration sequence identifier, or `null` for all upgrades.
     * @return null|string The resulting current sequence identifier.
     */
    public function up (string $to = null): ?string {
        return $this->db->transact(function() use ($to) {
            $current = $this->getCurrent();
            // walk oldest to newest
            foreach ($this->glob() as $sequence => $file) {
                if ($current and $to === $current) {
                    break;
                }
                if ($current >= $sequence) {
                    continue;
                }
                $this->db->transact(fn() => $this->getMigration($file)->up($this->db->getSchema()));
                $this->table->insert(['sequence' => $sequence]);
                $current = $sequence;
            }
            return $current;
        });
    }
}