<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
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
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   */
  public function __construct(LoggerChannelFactory $logger) {
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
      $dbconn_default = Database::getConnection('default', 'default');
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'subtopic');

      foreach ($fields as $field) {
        if ($field instanceof FieldConfig && $field->getType() === 'entity_reference') {

          $name = $field->getLabel();
          $table = 'node__' . $field->getName();
          $column = $field->getName() . '_target_id';

          $query = $dbconn_default->select($table, 'entrf');
          $query->fields('entrf', [$column]);
          $d7nids = $query->distinct()->execute()->fetchCol($column);

          $d9data = $this->lookupManager->lookupBySourceNodeId($d7nids);

          if (!empty($d9data)) {
            $this->logger->notice("Updating $name references.");
          }

          foreach ($d9data as $d7nid => $data) {
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
