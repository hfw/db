<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-07-15T07:33:08.710Z_Author */
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
            'foo' => Schema::T_STRING_NULL | Schema::I_UNIQUE,
            'id' => Schema::T_AUTOINCREMENT
        ], [
            Schema::TABLE_UNIQUE => [['bar','baz']]
        ]);
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
        $schema->dropTable('authors');
    }

};
