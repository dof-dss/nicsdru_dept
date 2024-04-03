<?php

declare(strict_types=1);

namespace Drupal\dept_migrate_users\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Creates QA User accounts after user migration.
 */
class PostMigrationCreateQaUsers implements EventSubscriberInterface {

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannel definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactory $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('dept_migrate');
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
        $this->logger->warning('QA_PASSWORD not set or blank.');
        return;
      }

      $path = \Drupal::service('extension.list.module')->getPath('dept_migrate_users');
      if (!file_exists($path . '/qa_accounts.json')) {
        $this->logger->warning('QA Accounts file not found.');
        return;
      }
      $accounts = json_decode(file_get_contents($path . '/qa_accounts.json'), TRUE);

      // Create and assign roles to QA accounts if not present on the site.
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
          $values['target_id'] = 'finance';

          $user->set(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD, $values);
          /* Don't set Domain Source as it'll cause a SQL error regarding a duplicate entry.
          $user->set(DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD, $values);
           */

          $result = $user->save();
          if ($result > 0) {
            $this->logger->notice('QA account ' . $account . ' created.');
          }
        }
      }
    }
  }

}
