<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-07-23T03:19:36.251Z_AuthorsToBooks */
return new class implements MigrationInterface {

    /**
     * @param Schema $schema
     */
    public function up($schema)
    {
        $schema->createTable('authors_to_books', [
            'author' => Schema::T_INT | Schema::I_PRIMARY,
            'book' => Schema::T_INT | Schema::I_PRIMARY
        ], [
            'author' => $schema['authors']['id'],
            'book' => $schema['books']['id']
        ]);
    }

    /**
     * @param Schema $schema
     */
    public function down($schema)
    {
        $schema->dropTable('authors_to_books');
    }

};
