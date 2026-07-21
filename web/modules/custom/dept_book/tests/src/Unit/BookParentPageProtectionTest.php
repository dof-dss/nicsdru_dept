<?php

namespace Drupal\Tests\dept_book\Unit;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dept_book\BookParentPageProtection;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the policy which protects book pages with children.
 *
 * @group dept_book
 */
class BookParentPageProtectionTest extends UnitTestCase {

  /**
   * Tests the parent-page protection decision.
   *
   * @dataProvider protectionProvider
   */
  public function testIsProtected(array $book_link, bool $has_override, bool $expected): void {
    $book_manager = $this->createMock(BookManagerInterface::class);
    $book_manager->expects($this->once())
      ->method('loadBookLink')
      ->with(42, FALSE)
      ->willReturn($book_link);

    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with(BookParentPageProtection::OVERRIDE_PERMISSION)
      ->willReturn($has_override);

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(42);

    $protection = new BookParentPageProtection($book_manager);
    $this->assertSame($expected, $protection->isProtected($node, $account));
  }

  /**
   * Provides book-outline scenarios for parent-page protection.
   */
  public static function protectionProvider(): array {
    return [
      'book root with children' => [
        ['nid' => 42, 'bid' => 42, 'pid' => 0, 'has_children' => 1],
        FALSE,
        TRUE,
      ],
      'nested book parent with children' => [
        ['nid' => 42, 'bid' => 10, 'pid' => 20, 'has_children' => 1],
        FALSE,
        TRUE,
      ],
      'book page without children' => [
        ['nid' => 42, 'bid' => 10, 'pid' => 20, 'has_children' => 0],
        FALSE,
        FALSE,
      ],
      'node outside a book' => [
        [],
        FALSE,
        FALSE,
      ],
    ];
  }

  /**
   * Tests that the override avoids the book lookup and protection.
   */
  public function testOverridePermissionAllowsParentChange(): void {
    $book_manager = $this->createMock(BookManagerInterface::class);
    $book_manager->expects($this->never())->method('loadBookLink');

    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with(BookParentPageProtection::OVERRIDE_PERMISSION)
      ->willReturn(TRUE);

    $node = $this->createMock(NodeInterface::class);
    $protection = new BookParentPageProtection($book_manager);
    $this->assertFalse($protection->isProtected($node, $account));
  }

  /**
   * Tests that an unsaved node cannot yet be a book parent.
   */
  public function testNewNodeIsNotProtected(): void {
    $book_manager = $this->createMock(BookManagerInterface::class);
    $book_manager->expects($this->never())->method('loadBookLink');

    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->never())->method('hasPermission');

    $node = $this->createMock(NodeInterface::class);
    $node->method('isNew')->willReturn(TRUE);

    $protection = new BookParentPageProtection($book_manager);
    $this->assertFalse($protection->isProtected($node, $account));
  }

  /**
   * Tests which moderation changes are blocked for a protected parent.
   *
   * @dataProvider archiveProvider
   */
  public function testArchiveProtection(string $new_state, ?string $original_state, bool $expected): void {
    $book_manager = $this->createMock(BookManagerInterface::class);
    $book_manager->method('loadBookLink')->willReturn(['has_children' => 1]);

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn(42);

    $protection = new BookParentPageProtection($book_manager);
    $this->assertSame($expected, $protection->isArchiveBlocked($node, $account, $new_state, $original_state));
  }

  /**
   * Provides moderation-state scenarios.
   */
  public static function archiveProvider(): array {
    return [
      'new archive transition' => ['archived', 'published', TRUE],
      'already archived page save' => ['archived', 'archived', FALSE],
      'publish transition' => ['published', 'draft', FALSE],
      'submit for review transition' => ['needs_review', 'draft', FALSE],
    ];
  }

}
