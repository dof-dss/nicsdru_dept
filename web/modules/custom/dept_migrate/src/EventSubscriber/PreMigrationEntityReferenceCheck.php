<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Pre migration subscriber to validate entity reference field targets ids.
 */
class PreMigrationEntityReferenceCheck implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityFieldManager definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db7conn;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   Entity Field Manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(EntityFieldManagerInterface $field_manager, LoggerChannelFactory $logger, Connection $connection) {
    $this->fieldManager = $field_manager;
    $this->logger = $logger->get('dept_migrate');
    $this->db7conn = $connection;
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::PRE_IMPORT][] = ['onMigratePreImport'];
    return $events;
  }

  /**
   * Handle pre import migration event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePreImport(MigrateImportEvent $event) {
    $event_id = $event->getMigration()->getBaseId();

    if (strpos($event_id, 'node_') === 0) {
      $bundle = substr($event_id, 5);

      $fields = $this->fieldManager->getFieldDefinitions('node', $bundle);

      $this->logger->notice("Checking entity reference fields for $bundle");

      // Iterate each bundle field and check for entity references.
      foreach ($fields as $field) {
        if ($field instanceof FieldConfig && $field->getType() === 'entity_reference') {
          $field_name = $field->getName();
          $field_settings = $field->getSettings();
          // Drupal 7 field table name.
          $field_table = 'field_data_' . $field_name;

          // Determine if the target bundles are nodes or terms.
          if ($field_settings['target_type'] == 'node') {
            $target_column = 'field_' . $field_name . '_id';

            if ($this->db7conn->schema()->fieldExists($field_table, $target_column)) {
              $this->db7conn->query("DELETE FROM {$field_table} WHERE $field_table.$target_column NOT IN (SELECT nid FROM {node}) AND $field_table.bundle = '$bundle'");
              $this->logger->notice("Deleted missing node references for $bundle field $field_name");
            }
          }
          elseif ($field_settings['target_type'] == 'taxonomy_term') {
            $target_column = 'field_' . $field_name . '_tid';

            if ($this->db7conn->schema()->fieldExists($field_table, $target_column)) {
              $this->db7conn->query("DELETE FROM {$field_table} WHERE $field_table.$target_column NOT IN (SELECT tid FROM {taxonomy_term_data}) AND $field_table.bundle = '$bundle'");
              $this->logger->notice("Deleted missing term references for $bundle field $field_name");
            }
          }
          else {
            return;
          }
        }
      }
    }
  }

}
