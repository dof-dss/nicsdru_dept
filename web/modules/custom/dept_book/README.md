# Departmental Book

## Cache expiration gotchas

Drupal's normal node cache expiration can miss book outline changes because the
outline may be updated directly without saving the affected parent nodes.

The module augments book navigation with cache tags for the book, current node,
and parent node, then expires the relevant old and new tags when a page joins,
leaves, or moves within a book.

This is applied to node insert, update, and deletion, and to changes made through
the Book outline, remove, and administration forms.

Changes which do not alter book or parent membership, such as reordering a
page under the same parent, do not trigger this additional expiration.
