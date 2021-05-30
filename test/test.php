#!/usr/bin/php
<?php
error_reporting(E_ALL);
ini_set('assert.exception', 1);
require '../vendor/autoload.php';

use Helix\DB;
use Helix\DB\Column;

$db = new DB('sqlite:test.db');
$db->setLogger(function($sql) {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2];
    echo "{$trace['function']} ==> {$sql}\n\n";
});
$db->beginTransaction();
$Author = $db->getRecord(Author::class);
$Book = $db->getRecord(Book::class);
$AuthorsToBooks = $db->getJunction(AuthorsToBooks::class);

// define alice.
$alice = new Author;
$alice->setName('Alice');
$alice['dob'] = 'January 1st';
$alice['favColor'] = 'blue';
assert($db->save($alice)); // insert
assert($db->save($alice)); // update
assert($alice == $Author->load($alice->getId()));

// define bob.
$bob = new Author;
$bob->setName('Bob');
$bob['dob'] = 'January 2nd';
$bob['favColor'] = 'red';
assert($db->save($bob));

// define their novel.
$novel = new Book;
$novel->setTitle("Alice and Bob's Novel");
$novel['note'] = 'Scribbles in the margins.';
assert($db->save($novel));

// link alice and bob to their novel.
assert($AuthorsToBooks->delete(['author' => $alice->getId()]) === 0);
assert($AuthorsToBooks->link(['author' => $alice->getId(), 'book' => $novel->getId()]) === 1);
assert($AuthorsToBooks->delete(['author' => $bob->getId()]) === 0);
assert($AuthorsToBooks->link(['author' => $bob->getId(), 'book' => $novel->getId()]) === 1);

// alice should have one book, with a note attribute.
$books = $AuthorsToBooks->find('book', ['author' => $alice->getId()]);
assert(count($books) === 1);
/** @var Book $book */
$book = $books->getFirst();
assert($book == $novel); // loose
assert(isset($book['note']));

// the novel should have two authors, alice and bob
$authors = $AuthorsToBooks->find('author', ['book' => $novel->getId()]);
assert(count($authors) === 2);
/** @var Author $author */
foreach ($authors as $author) {
    assert($author == $alice or $author == $bob); // loose
}

// remove and re-add bob from the list of authors
$AuthorsToBooks->delete(['author' => $bob->getId()]);
assert(count($authors) === 1);
$AuthorsToBooks->link(['author' => $bob->getId(), 'book' => $novel->getId()]);
assert($AuthorsToBooks->count(['author' => $bob->getId()]) === 1);

// eav search for alice
$authors = $Author->find([], [
    'attributes' => [
        'dob' => 'January 1st',
        'favColor' => function(Column $value) {
            return $value->isNotEqual('red'); // ! bob's color
        }
    ]
]);
assert(count($authors) === 1);
/** @var Author $author */
$author = $authors->getFirst();
assert($author->getName() === 'Alice');

// test isEqual(Select)
$authors = $Author->select()->where(
    $Author['id']->isEqual($Author->select([$Author['id']])->where(
        $Author['name']->isEqual('Alice')
    ))
);
assert(count($authors) === 1);
$author = $authors->getFirst();
assert($author->getName() === 'Alice');

// test isLessOrEqual(Select)
$authors = $Author->select()->where(
    $Author['id']->isLessOrEqual($Author->select([$Author['id']])->where(
        $Author['id']->isEqual(1)
    ))
);
assert(count($authors) === 1);
$author = $authors->getFirst();
assert($author->getName() === 'Alice');

// test isLessOrEqual(Select,ANY)
$authors = $Author->select()->where(
    $Author['id']->isLessOrEqual($Author->select([$Author['id']])->where(
        $Author['id']->isEqual(1)
    ), 'ANY')
);
assert(count($authors) === 1);
$author = $authors->getFirst();
assert($author->getName() === 'Alice');

// test column->count()
$count = (int)$Author->select([$Author['id']->getCount('DISTINCT')])
    ->execute()
    ->fetchColumn();
assert($count === 2);

// test text functions
$upperName = $Author->select([$Author['name']->getUpper()])
    ->where($Author['id']->isEqual($alice->getId()))
    ->execute()
    ->fetchColumn();
assert($upperName === 'ALICE');

// test FLOOR (sqlite custom function)
$floorId = (int)$Author->select([$Author['id']->floor()])
    ->where($Author['id']->isEqual($alice->getId()))
    ->execute()
    ->fetchColumn();
assert($floorId === 1);

// test Choice
$choice = $Author['name']->switch(['Alice' => 'ALICE', 'Bob' => 'BOB']);
$names = $Author->select(['name' => $choice])->getAll();
assert($names === [['name' => 'ALICE'], ['name' => 'BOB']]);

// test nested Choice by switching back
$choice = $choice->switch(['ALICE' => 'Alice', 'BOB' => 'Bob']);
$names = $Author->select(['name' => $choice])->getAll();
assert($names === [['name' => 'Alice'], ['name' => 'Bob']]);
