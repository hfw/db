<?php

namespace Helix\DB;

use Helix\DB;

/**
 * Migrations must implement this.
 *
 * Migration files must be contained in their own directory.
 * The default directory is `migrations/default/`
 *
 * Migration files must be named `<SEQUENCE>.php`, where `SEQUENCE` is a non-zero ascending identifier.
 * The file must `return` an instance of this interface (e.g. an anonymous class).
 *
 * Standard practice is to have file names prefixed with a date-time,
 * followed by a description of what they do.
 *
 * Migrations are given the {@link Schema} helper, but can directly execute SQL if needed.
 *
 * Each migration is performed within a transaction savepoint.
 *
 * See the test migration in `test/migrations/default/`
 *
 * @see DB::getMigrator()
 * @see Migrator::glob()
 */
interface MigrationInterface {

    /**
     * @param Schema $schema
     * @return void
     */
    public function down ($schema);

    /**
     * @param Schema $schema
     * @return void
     */
    public function up ($schema);
}