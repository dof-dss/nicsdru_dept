<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Post migration subscriber for entity reference fields.
 */
class PostMigrationEntityRefUpdateSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityFieldManager definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Lookup manager.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   Entity Field Manager.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookup_manager
   *   Lookup Manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   */
  public function __construct(EntityFieldManagerInterface $field_manager, MigrateUuidLookupManager $lookup_manager, LoggerChannelFactory $logger) {
    $this->fieldManager = $field_manager;
    $this->lookupManager = $lookup_manager;
    $this->logger = $logger->get('dept_migrate');
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
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

    if (strpos($event_id, 'node_') === 0) {
      $bundle = substr($event_id, 5);
      $dbconn_default = Database::getConnection('default', 'default');
      $fields = $this->fieldManager->getFieldDefinitions('node', $bundle);

      $this->logger->notice("Updating entity reference fields for $bundle");
      foreach ($fields as $field) {
        if ($field instanceof FieldConfig && $field->getType() === 'entity_reference') {

          $name = $field->getLabel();
          $table = 'node__' . $field->getName();
          $column = $field->getName() . '_target_id';

          $query = $dbconn_default->select($table, 'entrf');
          $query->fields('entrf', [$column]);
          $d7nids = $query->distinct()->execute()->fetchCol($column);

          if (empty($d7nids)) {
            $this->logger->error("Couldn't find any d7 nids for ${column}");
            continue;
          }

          $d9data = $this->lookupManager->lookupBySourceNodeId($d7nids);

          if (!empty($d9data)) {
            $this->logger->notice("Updating $name references.");
          }

          foreach ($d9data as $d7nid => $data) {
            if (empty($data['nid']) || empty($d7nid)) {
              $this->logger->error("Couldn't set an empty value for ${column} in ${table}. data[nid] was ${data['nid']} and d7nid was ${d7nid}");
              continue;
            }

            $num_updated = $dbconn_default->update($table)
              ->fields([$column => $data['nid']])
              ->condition($column, $d7nid, '=')
              ->execute();
            $this->logger->notice("Updated $num_updated entries for $d7nid");
          }
        }
      }
    }
  }

}
