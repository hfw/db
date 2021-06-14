<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

return new class implements MigrationInterface {

    /**
     * @param Schema $schema
     */
    public function down ($schema) {
        $db = $schema->getDb();
        $schema->dropTable($db->getJunction(AuthorsToBooks::class));

        $Book = $db->getRecord(Book::class);
        $schema->dropTable($Book->getEav('attributes'));
        $schema->dropTable($Book);

        $Author = $db->getRecord(Author::class);
        $schema->dropTable($Author->getEav('attributes'));
        $schema->dropTable($Author);
    }

    /**
     * @param Schema $schema
     */
    public function up ($schema) {
        $db = $schema->getDb();

        $Author = $db->getRecord(Author::class);
        $schema->createRecordTable($Author);
        $schema->createEavTable($Author, 'attributes');

        $Book = $db->getRecord(Book::class);
        $schema->createRecordTable($Book);
        $schema->createEavTable($Book, 'attributes');

        $schema->createJunctionTable($db->getJunction(AuthorsToBooks::class));
    }
};