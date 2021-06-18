<?php

/**
 * Marker interface specifying a many-to-many junction table.
 *
 * Verifies the `@junction`, `@foreign`, and shortened `@for` annotations.
 *
 * @junction authors_to_books
 * @foreign author Author
 * @for book Book
 */
interface AuthorsToBooks
{

}
