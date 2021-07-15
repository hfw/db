<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-07-15T16:48:02.162Z_Author */
return new class implements MigrationInterface {

    /**
     * @param Schema $schema
     */
    public function up($schema)
    {
        $schema->createTable('authors', [
            'name' => Schema::T_STRING,
            'bar' => Schema::T_STRING_NULL,
            'baz' => Schema::T_STRING_NULL,
            'foo' => Schema::T_STRING_NULL,
            'id' => Schema::T_AUTOINCREMENT
        ]);
        $schema->addUniqueKeyConstraint('authors', ['bar', 'baz']);
        $schema->addUniqueKeyConstraint('authors', ['foo']);
        $schema->createTable('authors_eav', [
            'entity' => Schema::T_INT,
            'attribute' => Schema::T_STRING,
            'value' => Schema::T_STRING
        ], [
            Schema::TABLE_PRIMARY => ['entity', 'attribute'],
            Schema::TABLE_FOREIGN => ['entity' => $schema['authors']['id']]
        ]);
    }

    /**
     * @param Schema $schema
     */
    public function down($schema)
    {
        $schema->dropTable('authors_eav');
        $schema->dropUniqueKeyConstraint('authors', ['foo']);
        $schema->dropUniqueKeyConstraint('authors', ['bar', 'baz']);
        $schema->dropTable('authors');
    }

};
