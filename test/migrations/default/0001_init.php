<?php

use Helix\DB\MigrationInterface;

final class init implements MigrationInterface {

    public function down ($db) {
        $schema = $db->getSchema();

        $schema->dropTable($db->getJunction(AuthorsToBooks::class));

        $Book = $db->getRecord(Book::class);
        $schema->dropTable($Book->getEav('attributes'));
        $schema->dropTable($Book);

        $Author = $db->getRecord(Author::class);
        $schema->dropTable($Author->getEav('attributes'));
        $schema->dropTable($Author);
    }

    public function up ($db) {
        $schema = $db->getSchema();

        $Author = $db->getRecord(Author::class);
        $schema->createRecordTable($Author);
        $schema->createEavTable($Author, 'attributes');

        $Book = $db->getRecord(Book::class);
        $schema->createRecordTable($Book);
        $schema->createEavTable($Book, 'attributes');

        $schema->createJunctionTable($db->getJunction(AuthorsToBooks::class));
    }
}