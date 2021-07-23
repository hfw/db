<?php

use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-07-23T03:08:03.077Z_Author */
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
        $schema->addUniqueKeyConstraint('authors', ['foo']);
        $schema->addUniqueKeyConstraint('authors', ['bar', 'baz']);
        $schema->createTable('authors_eav', [
            'entity' => Schema::T_INT | Schema::I_PRIMARY,
            'attribute' => Schema::T_STRING | Schema::I_PRIMARY,
            'value' => Schema::T_STRING
        ], [
            'entity' => $schema['authors']['id']
        ]);
    }

    /**
     * @param Schema $schema
     */
    public function down($schema)
    {
        $schema->dropTable('authors_eav');
        $schema->dropUniqueKeyConstraint('authors', ['bar', 'baz']);
        $schema->dropUniqueKeyConstraint('authors', ['foo']);
        $schema->dropTable('authors');
    }

};
