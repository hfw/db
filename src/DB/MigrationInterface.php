<?php

namespace Helix\DB;

use Helix\DB;

/**
 * Migrations must implement this.
 *
 * Migration files must be contained in their own directory.
 * The default directory is `migrations/` (see {@link DB::getMigrator()} and {@link Migrator::glob()})
 *
 * Migration files must be named `<SEQUENCE>_<CLASS>.php`,
 * where `SEQUENCE` is a non-zero ascending identifier,
 * and `CLASS` is the name of the class implementing this interface.
 * `SEQUENCE` must not contain underscores. `CLASS` on the other hand may contain underscores.
 * The implementing class must not be namespaced.
 * No two migrations may share the same class name.
 * Migrations must allow construction without arguments.
 *
 * Standard practice is to use a date-time for `SEQUENCE`,
 * and to name `CLASS` a description of the migration's work.
 *
 * Migrations can execute direct SQL or use the {@link Schema} helpers.
 *
 * Each migration is performed within a transaction savepoint.
 *
 * See the test migration in `test/migrations/default/`
 */
interface MigrationInterface {

    /**
     * @param DB $db
     * @return void
     */
    public function down ($db);

    /**
     * @param DB $db
     * @return void
     */
    public function up ($db);
}