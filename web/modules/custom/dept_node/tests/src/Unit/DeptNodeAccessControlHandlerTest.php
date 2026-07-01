<?php

namespace Drupal\Tests\dept_node\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\dept_node\DeptNodeAccessControlHandler;
use Drupal\node\NodeInterface;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the custom node revision delete access.
 *
 * @group dept_node
 */
class DeptNodeAccessControlHandlerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $cache_contexts_manager = $this->createMock(CacheContextsManager::class);
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests that the custom permission only allows non-default revisions.
   *
   * A valid allowed case is `$default_revision = FALSE` with
   * `$expected = TRUE`: the revision is historical and may be deleted. A
   * valid denied case is `$default_revision = TRUE` with `$expected = FALSE`:
   * the revision is current and must be retained. Inverting either expected
   * value would describe an invalid access model.
   *
   * @param bool $default_revision
   *   TRUE when testing the current/default revision, or FALSE when testing a
   *   historical revision.
   * @param bool $expected
   *   TRUE when deletion should be allowed, or FALSE when it should be denied.
   *
   * @dataProvider revisionAccessProvider
   */
  public function testRevisionDeleteAccess(bool $default_revision, bool $expected): void {
    $permission = DeptNodeAccessControlHandler::revisionDeletePermission('article');

    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with($permission)
      ->willReturn(TRUE);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('isDefaultRevision')->willReturn($default_revision);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    $handler = new TestDeptNodeAccessControlHandler();

    $result = $handler->checkNodeAccess($node, 'delete revision', $account);
    $this->assertSame($expected, $result->isAllowed());
    $this->assertSame(!$expected, $result->isForbidden());
    $this->assertContains('user.permissions', $result->getCacheContexts());
  }

  /**
   * Provides revision default status and expected access.
   *
   * Each item contains `[is default revision, access should be allowed]`.
   * For example, `[FALSE, TRUE]` is correct for a historical revision and
   * `[TRUE, FALSE]` is correct for the current revision. Values such as
   * `[TRUE, TRUE]` would be unsafe because they would allow the live revision
   * to be deleted.
   *
   * @return array<string, array{bool, bool}>
   *   Revision status and the corresponding expected access result.
   */
  public static function revisionAccessProvider(): array {
    return [
      'non-default revision' => [FALSE, TRUE],
      'default revision' => [TRUE, FALSE],
    ];
  }

  /**
   * Tests that the add-on permission does not grant node delete access.
   *
   * This represents an author or supervisor who also has the Homepage
   * supervisor add-on role. A correct result allows deletion of a historical
   * revision but denies deletion of the featured-content-list node. Allowing
   * both operations would make the narrowly scoped add-on permission unsafe.
   */
  public function testRevisionPermissionDoesNotGrantNodeDeleteAccess(): void {
    $permission = DeptNodeAccessControlHandler::revisionDeletePermission('article');

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(function (string $requested_permission) use ($permission): bool {
        // Grant only the custom revision-delete permission. In particular,
        // this account has no permission to delete the node itself.
        if ($requested_permission === $permission) {
          return TRUE;
        }

        return FALSE;
      });

    $node = $this->createNode(FALSE);
    $grant_storage = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $grant_storage->expects($this->once())
      ->method('access')
      ->with($node, 'delete', $account)
      ->willReturn(AccessResult::forbidden());

    $handler = new TestDeptNodeAccessControlHandler($grant_storage);

    $this->assertTrue($account->hasPermission($permission));
    $this->assertTrue($handler->checkNodeAccess($node, 'delete revision', $account)->isAllowed());
    $this->assertTrue($handler->checkNodeAccess($node, 'delete', $account)->isForbidden());
  }

  /**
   * Tests that node delete access does not grant revision delete access.
   *
   * This account is deliberately allowed to delete the node but has no
   * revision-delete permission. A correct result allows the `delete` operation
   * and leaves `delete revision` neutral. Treating node-delete access alone as
   * sufficient revision permission would couple two independent permissions.
   */
  public function testNodeDeleteAccessDoesNotGrantRevisionPermission(): void {
    $permission = DeptNodeAccessControlHandler::revisionDeletePermission('article');
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);

    $node = $this->createNode(FALSE);
    $grant_storage = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $grant_storage->expects($this->once())
      ->method('access')
      ->with($node, 'delete', $account)
      ->willReturn(AccessResult::allowed());

    $handler = new TestDeptNodeAccessControlHandler($grant_storage);

    $this->assertFalse($account->hasPermission($permission));
    $this->assertTrue($handler->checkNodeAccess($node, 'delete', $account)->isAllowed());
    $this->assertTrue($handler->checkNodeAccess($node, 'delete revision', $account)->isNeutral());
  }

  /**
   * Creates a cacheable node revision mock.
   *
   * Pass FALSE for a normal historical revision that may be deleted when the
   * account has permission. Pass TRUE to represent the current/default
   * revision, which must never be deleted through the revision operation. This
   * value describes revision status; it does not describe published status.
   *
   * @param bool $default_revision
   *   Whether the mock represents the current/default revision.
   *
   * @return \Drupal\node\NodeInterface
   *   A revision mock for the article content type.
   */
  private function createNode(bool $default_revision): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('isDefaultRevision')->willReturn($default_revision);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);
    return $node;
  }

}

/**
 * Exposes the protected access check for unit testing.
 */
class TestDeptNodeAccessControlHandler extends DeptNodeAccessControlHandler {

  /**
   * Avoids services unused by the custom access branch under test.
   *
   * NULL is sufficient when a test only exercises the custom `delete revision`
   * branch. Supply grant storage when testing a normal node operation such as
   * `delete`; omitting it in that case would leave the parent handler unable to
   * perform its node-grant check.
   *
   * @param \Drupal\node\NodeGrantDatabaseStorageInterface|null $grant_storage
   *   Node grant storage for tests which delegate to the parent handler.
   */
  public function __construct(?NodeGrantDatabaseStorageInterface $grant_storage = NULL) {
    if ($grant_storage) {
      $this->grantStorage = $grant_storage;
    }
  }

  /**
   * Calls the node access check.
   *
   * Use `delete revision` to exercise the custom bundle permission. Use
   * `delete` to verify that ordinary node deletion still follows Drupal core's
   * node-grant path. The node and account mocks must provide the methods and
   * permissions required by the selected operation.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node or node revision whose access is being checked.
   * @param string $operation
   *   The entity operation, for example `delete revision` or `delete`.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account whose combined role permissions are being tested.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The handler's allowed, forbidden, or neutral result.
   */
  public function checkNodeAccess(NodeInterface $node, string $operation, AccountInterface $account) {
    return $this->checkAccess($node, $operation, $account);
  }

}
