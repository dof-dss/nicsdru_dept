<?php declare(strict_types = 1);

namespace Drupal\dept_migrate_users\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Creates QA User accounts after user migration
 */
class PostMigrationCreateQaUsers implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Constructor.
   */
  public function __construct(private readonly EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Handle post import migration event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $event_id = $event->getMigration()->getBaseId();

    if ($event_id === 'users') {
      $pass = getenv('QA_PASSWORD');

      if (empty($pass)) {
        return;
      }

      $path = \Drupal::service('extension.list.module')->getPath('dept_migrate_users');
      if (!file_exists($path . '/qa_accounts.json')) {
        return;
      }
      $accounts = json_decode(file_get_contents($path . '/qa_accounts.json'), TRUE);

      foreach ($accounts as $account => $roles) {
        $account = 'nw_test_' . $account;
        $user_query = $this->entityTypeManager->getStorage('user')->getQuery();
        $uid = $user_query->accessCheck(FALSE)
          ->condition('name', $account)
          ->range(0, 1)
          ->execute();
        if (empty($uid)) {
          $user = User::create();
          $user->setUsername($account);
          $user->setPassword($pass);
          $user->enforceIsNew();
          $user->setEmail($account . '@localhost.com');
          foreach ($roles as $role) {
            $user->addRole($role);
          }
          $user->save();
        }
      }
    }
  }

}
