<?php

namespace Drupal\dept_book;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Determines whether a book parent may be archived or deleted.
 */
class BookParentPageProtection {

  /**
   * Permission which allows the book parent safeguards to be overridden.
   */
  public const OVERRIDE_PERMISSION = 'override book parent protection';

  /**
   * Constructs the book parent page protection service.
   */
  public function __construct(protected BookManagerInterface $bookManager) {}

  /**
   * Checks whether the account is prevented from changing the parent page.
   */
  public function isProtected(NodeInterface $node, AccountInterface $account): bool {
    if ($node->isNew() || $account->hasPermission(self::OVERRIDE_PERMISSION)) {
      return FALSE;
    }

    $book_link = $this->bookManager->loadBookLink($node->id(), FALSE);
    return !empty($book_link['has_children']);
  }

  /**
   * Checks whether a new moderation state represents a blocked archive.
   */
  public function isArchiveBlocked(NodeInterface $node, AccountInterface $account, string $new_state, ?string $original_state = NULL): bool {
    return $new_state === 'archived'
      && $original_state !== 'archived'
      && $this->isProtected($node, $account);
  }

}
