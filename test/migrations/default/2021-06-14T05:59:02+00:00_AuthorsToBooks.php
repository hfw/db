<?php
use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-06-14T05:59:02+00:00_AuthorsToBooks */
return new class implements MigrationInterface {

    /**
     * @var Schema $schema
     */
    public function up ($schema)
    {
		$schema->createTable('AuthorsToBooks', [
            'author' => Schema::T_INT_STRICT,
            'book' => Schema::T_INT_STRICT
        ], [
            Schema::TABLE_PRIMARY => ['author', 'book'],
            Schema::TABLE_FOREIGN => [
                'author' => $schema['Author']['id'],
                'book' => $schema['Book']['id']
            ]
        ]);
    }

    /**
     * @var Schema $schema
     */
    public function down ($schema)
    {
		$schema->dropTable('AuthorsToBooks');
    }

};
