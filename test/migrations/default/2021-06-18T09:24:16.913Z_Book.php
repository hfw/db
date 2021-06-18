<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-06-18T09:24:16.913Z_Book */
return new class implements MigrationInterface {

    /**
     * @var Schema $schema
     */
    public function up($schema)
    {
        $schema->createTable('books', [
            'published' => Schema::T_DATETIME,
            'title' => Schema::T_STRING,
            'id' => Schema::T_AUTOINCREMENT
        ]);
        $schema->createTable('books_eav', [
            'entity' => Schema::T_INT,
            'attribute' => Schema::T_STRING,
            'value' => Schema::T_STRING
        ], [
            Schema::TABLE_PRIMARY => ['entity', 'attribute'],
            Schema::TABLE_FOREIGN => ['entity' => $schema['books']['id']]
        ]);
    }

    /**
     * @var Schema $schema
     */
    public function down($schema)
    {
        $schema->dropTable('books_eav');
        $schema->dropTable('books');
    }

};
