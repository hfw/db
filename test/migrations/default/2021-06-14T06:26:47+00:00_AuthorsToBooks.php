<?php
use Helix\DB\MigrationInterface;
use Helix\DB\Schema;

/** 2021-06-14T06:26:47+00:00_AuthorsToBooks */
return new class implements MigrationInterface {

    /**
     * @var Schema $schema
     */
    public function up ($schema)
    {
		$schema->createTable('authors_to_books', [
            'author' => Schema::T_INT_STRICT,
            'book' => Schema::T_INT_STRICT
        ], [
            Schema::TABLE_PRIMARY => ['author', 'book'],
            Schema::TABLE_FOREIGN => [
                'author' => $schema['authors']['id'],
                'book' => $schema['books']['id']
            ]
        ]);
    }

    /**
     * @var Schema $schema
     */
    public function down ($schema)
    {
		$schema->dropTable('authors_to_books');
    }

};
