#!/bin/bash -e

../bin/helix.db.migrate.php --record=Author
../bin/helix.db.migrate.php --record=Book
../bin/helix.db.migrate.php --junction=AuthorsToBooks
