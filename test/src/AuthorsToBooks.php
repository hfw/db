<?php

/**
 * Marker interface specifying a many-to-many junction table.
 *
 * Verifies the `@junction`, `@foreign`, and `@for` annotations.
 *
 * @junction AuthorsToBooks
 * @foreign author Author
 * @for book Book
 */
interface AuthorsToBooks {

}