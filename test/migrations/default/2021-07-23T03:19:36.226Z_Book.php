<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-07-23T03:19:36.226Z_Book */
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
        $schema->addUniqueKey('books', ['foo']);
        $schema->addUniqueKey('books', ['bar', 'baz']);
        $schema->createTable('books_eav', [
            'entity' => Schema::T_INT | Schema::I_PRIMARY,
            'attribute' => Schema::T_STRING | Schema::I_PRIMARY,
            'value' => Schema::T_STRING
        ], [
            'entity' => $schema['books']['id']
        ]);
    }

    /**
     * @param Schema $schema
     */
    public function down($schema)
    {
        $schema->dropTable('books_eav');
        $schema->dropUniqueKey('books', ['bar', 'baz']);
        $schema->dropUniqueKey('books', ['foo']);
        $schema->dropTable('books');
    }

};
