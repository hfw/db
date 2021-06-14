<?php
use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-06-14T05:58:51+00:00_Author */
return new class implements MigrationInterface {

    /**
     * @var Schema $schema
     */
    public function up ($schema)
    {
		$schema->createTable('Author',[
            'name' => Schema::T_STRING_STRICT,
            'id' => Schema::T_AUTOINCREMENT
        ]);
		$schema->createTable('AuthorEAV', [
            'entity' => Schema::T_INT_STRICT,
            'attribute' => Schema::T_STRING_STRICT,
            'value' => Schema::T_STRING
        ], [
            Schema::TABLE_PRIMARY => ['entity', 'attribute'],
            Schema::TABLE_FOREIGN => $schema['Author']['id']
        ]);
    }

    /**
     * @var Schema $schema
     */
    public function down ($schema)
    {
		$schema->dropTable('AuthorEAV');
		$schema->dropTable('Author');
    }

};
