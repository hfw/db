<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-07-15T16:48:02.191Z_Book */
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
            'foo' => Schema::T_STRING_NULL,
            'id' => Schema::T_AUTOINCREMENT
        ]);
        $schema->addUniqueKeyConstraint('books', ['bar', 'baz']);
        $schema->addUniqueKeyConstraint('books', ['foo']);
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
        $schema->dropUniqueKeyConstraint('books', ['foo']);
        $schema->dropUniqueKeyConstraint('books', ['bar', 'baz']);
        $schema->dropTable('books');
    }

};
