<?php

/**
 * Marker interface specifying a many-to-many junction table.
 *
 * Verifies the `@junction` and `@foreign` annotations.
 *
 * @junction authors_to_books
 * @foreign author Author
 * @foreign book Book
 */
interface AuthorsToBooks
{

}
