<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-07-15T07:33:08.736Z_Book */
return new class implements MigrationInterface {

    /**
     * @param Schema $schema
     */
    public function up($schema)
    {
        $schema->createTable('books', [
            'published' => Schema::T_DATETIME,
            'title' => Schema::T_STRING,
            'bar' => Schema::T_STRING_NULL,
            'baz' => Schema::T_STRING_NULL,
            'foo' => Schema::T_STRING_NULL | Schema::I_UNIQUE,
            'id' => Schema::T_AUTOINCREMENT
        ], [
            Schema::TABLE_UNIQUE => [['bar','baz']]
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
     * @param Schema $schema
     */
    public function down($schema)
    {
        $schema->dropTable('books_eav');
        $schema->dropTable('books');
    }

};
