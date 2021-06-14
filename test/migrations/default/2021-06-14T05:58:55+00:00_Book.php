<?php
use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-06-14T05:58:55+00:00_Book */
return new class implements MigrationInterface {

    /**
     * @var Schema $schema
     */
    public function up ($schema)
    {
		$schema->createTable('Book',[
            'title' => Schema::T_STRING_STRICT,
            'id' => Schema::T_AUTOINCREMENT
        ]);
		$schema->createTable('BookEAV', [
            'entity' => Schema::T_INT_STRICT,
            'attribute' => Schema::T_STRING_STRICT,
            'value' => Schema::T_STRING
        ], [
            Schema::TABLE_PRIMARY => ['entity', 'attribute'],
            Schema::TABLE_FOREIGN => $schema['Book']['id']
        ]);
    }

    /**
     * @var Schema $schema
     */
    public function down ($schema)
    {
		$schema->dropTable('BookEAV');
		$schema->dropTable('Book');
    }

};
