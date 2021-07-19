#!/usr/bin/php
<?php
include_once "../bin/.init.php";

use Helix\DB;
use Helix\DB\Column;

$now = new DateTimeImmutable();
$utc = new DateTimeZone('UTC');

$db = DB::fromConfig();
$db->setLogger(function ($sql) {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4)[3];
    echo "{$trace['function']} ==> {$sql}\n\n";
});

// get access objects
$Author = $db->getRecord(Author::class);
$Book = $db->getRecord(Book::class);
$AuthorsToBooks = $db->getJunction(AuthorsToBooks::class);

// test transactions and savepoints, so we can auto-rollback at the end of the script.
$transaction = $db->newTransaction();

// test migrations
$migrator = $db->getMigrator();
$migrator->up();
$migrator->down(); // once
$migrator->up();
$migrator->down(0); // all the way
$migrator->up();

$savepoint = $db->newTransaction();
assert($Author->select()->getAll() === []);
//exit;

// define alice.
$alice = new Author;
$alice->setName('Alice');
$alice['dob'] = 'January 1st';
$alice['favColor'] = 'blue';
assert($Author->save($alice)); // insert
assert($Author->save($alice)); // update
assert($alice->is($Author->load($alice->getId()))); // explicit id to avoid reloading alice

// define bob.
$bob = new Author;
$bob->setName('Bob');
$bob['dob'] = 'January 2nd';
$bob['favColor'] = 'red';
assert($Author->save($bob));

// define their novel.
$novel = new Book;
$novel->setTitle("Alice and Bob's Novel");
$novel->setPublished($now);
$novel['note'] = 'Scribbles in the margins.';
assert($Book->save($novel));

// link alice and bob to their novel.
assert($AuthorsToBooks->delete(['author' => $alice]) === 0);
assert($AuthorsToBooks->link(['author' => $alice, 'book' => $novel]) === 1);
assert($AuthorsToBooks->delete(['author' => $bob]) === 0);
assert($AuthorsToBooks->link(['author' => $bob, 'book' => $novel]) === 1);

// alice should have one book, with a note attribute.
$books = $AuthorsToBooks->findAll('book', ['author' => $alice]);
assert(count($books) === 1);
/** @var Book $book */
$book = $books->getFirst();
assert($book->getTitle() === $novel->getTitle());
assert($book->getPublished()->getTimestamp() === $novel->getPublished()->getTimestamp());
assert($book['note'] === $novel['note']);

// the novel should have two authors, alice and bob
$authors = $AuthorsToBooks->findAll('author', ['book' => $novel]);
assert(count($authors) === 2);
/** @var Author $author */
foreach ($authors as $author) {
    assert($author->is($alice) or $author->is($bob));
}

// remove and re-add bob from the list of authors
$AuthorsToBooks->unlink(['author' => $bob]);
assert(count($authors) === 1);
$AuthorsToBooks->link(['author' => $bob, 'book' => $novel]);
assert($AuthorsToBooks->count(['author' => $bob]) === 1);

// eav search for alice
$authors = $Author->findAll([], [
    'attributes' => [
        'dob' => 'January 1st',
        'favColor' => fn(Column $value) => $value->isNotEqual('red') // ! bob's color
    ]
]);
assert(count($authors) === 1);
/** @var Author $author */
$author = $authors->getFirst();
assert($author->getName() === 'Alice');

// test isEqual(Select)
$authors = $Author->loadAll()->where(
    $Author['id']->isEqual($Author->select('id')->where(
        $Author['name']->isEqual('Alice')
    ))
);
assert(count($authors) === 1);
$author = $authors->getFirst();
assert($author->getName() === 'Alice');

// test isLte(Select)
$authors = $Author->loadAll()->where(
    $Author['id']->isLte($Author->select('id')->where(
        $Author['id']->isEqual(1)
    ))
);
assert(count($authors) === 1);
$author = $authors->getFirst();
assert($author->getName() === 'Alice');

// test isLteAny(Select)
$authors = $Author->loadAll()->where(
    $Author['id']->isLteAny($Author->select('id')->where(
        $Author['id']->isEqual(1)
    ))
);
assert(count($authors) === 1);
$author = $authors->getFirst();
assert($author->getName() === 'Alice');

// test array-access aggregate result using a messy function name
$count = $Author['id'][' COUNT ( DISTINCT ) ']; // ->countDistinct()
assert($count == 2);

// test text functions
$upperName = $Author->select([$Author['name']->upper()])
    ->where($Author['id']->is($alice))
    ->getResult();
assert($upperName === 'ALICE');

// test FLOOR (sqlite custom function)
$floorId = (int)$Author->select([$Author['id']->floor()])
    ->where($Author['id']->is($alice))
    ->getResult();
assert($floorId === 1);

// test Choice
$choice = $Author['name']->switch(['Alice' => 'ALICE', 'Bob' => 'BOB']);
$names = $Author->select(['name' => $choice])->getAll();
assert($names === [['name' => 'ALICE'], ['name' => 'BOB']]);

// test nested Choice by switching back
$choice = $choice->switch(['ALICE' => 'Alice', 'BOB' => 'Bob']);
$names = $Author->select(['name' => $choice])->getAll();
assert($names === [['name' => 'Alice'], ['name' => 'Bob']]);
