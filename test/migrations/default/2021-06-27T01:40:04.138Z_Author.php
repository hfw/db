<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-06-27T01:40:04.138Z_Author */
return new class implements MigrationInterface {

    /**
     * @param Schema $schema
     */
    public function up($schema)
    {
        $schema->createTable('authors', [
            'name' => Schema::T_STRING,
            'id' => Schema::T_AUTOINCREMENT
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