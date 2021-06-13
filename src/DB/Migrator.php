<?php

namespace Helix\DB;

use Helix\DB;

/**
 * @method static static factory(DB $db, string $dir);
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
     * @return false|string The resulting current sequence identifier.
     */
    public function down (string $to = null) {
        return $this->db->transact(function() use ($to) {
            $current = $this->getCurrent();
            // walk newest to oldest
            foreach (array_reverse($this->glob(), true) as $sequence => $spec) {
                if ($current and $to === $current) {
                    break;
                }
                if ($current < $sequence) {
                    continue;
                }
                $this->db->transact(function() use ($sequence, $spec) {
                    $this->getMigration($spec)->down($this->db);
                    $this->table->delete(['sequence' => $sequence]);
                });
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
    protected function getMigration (array $spec) {
        include_once "{$spec['file']}";
        $migration = new $spec['class'];
        assert($migration instanceof MigrationInterface);
        return $migration;
    }

    /**
     * Scans the migration directory for `<SEQUENCE>_<CLASS>.php` files.
     *
     * @return array[] [ file => spec array ]
     */
    protected function glob () {
        $specs = [];
        $files = glob("{$this->dir}/?*_?*.php");
        foreach ($files as $file) {
            preg_match('/^(?<sequence>[^_]+)_(?<class>.*)\.php$/', basename($file), $spec);
            $specs[$spec['sequence']] = [
                'file' => $file,
                'class' => "\\{$spec['class']}"
            ];
        }
        return $specs;
    }

    /**
     * Migrates up within a transaction.
     *
     * @param null|string $to Migration sequence identifier, or `null` for all upgrades.
     * @return false|string The resulting current sequence identifier.
     */
    public function up (string $to = null) {
        return $this->db->transact(function() use ($to) {
            $current = $this->getCurrent();
            // walk oldest to newest
            foreach ($this->glob() as $sequence => $spec) {
                if ($current and $to === $current) {
                    break;
                }
                if ($current >= $sequence) {
                    continue;
                }
                $this->db->transact(function() use ($sequence, $spec) {
                    $this->getMigration($spec)->up($this->db);
                    $this->table->insert(['sequence' => $sequence]);
                });
                $current = $sequence;
            }
            return $current;
        });
    }
}