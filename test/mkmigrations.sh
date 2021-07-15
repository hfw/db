#!/bin/bash -e

../bin/migrate.php --record=Author
../bin/migrate.php --record=Book
../bin/migrate.php --junction=AuthorsToBooks
