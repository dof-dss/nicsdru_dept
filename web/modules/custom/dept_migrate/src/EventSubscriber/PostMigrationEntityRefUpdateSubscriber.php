<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\views\Views;
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
   * Drupal\Core\Logger\LoggerChannel definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

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
    $this->dbconn = $connection;
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
      $fields = $this->fieldManager->getFieldDefinitions('node', $bundle);

      $this->logger->notice("Updating entity reference fields for $bundle");

      foreach ($fields as $field) {
        if ($field instanceof FieldConfig && $field->getType() === 'entity_reference') {
          $field_settings = $field->getSettings();
          $target_entity = $field_settings['target_type'];

          /* Ignore migration tables for Media and Taxonomy as we
          don't need to update those types of reference field. */
          if ($target_entity === 'media' || $target_entity === 'taxonomy_term') {
            continue;
          }

          // Determine the reference types the field targets.
          if ($field_settings['handler'] === 'views') {
            $view = Views::getView($field_settings['handler_settings']['view']['view_name']);
            $view->setDisplay($field_settings['handler_settings']['view']['display_name']);
            $display = $view->getDisplay();
            $target_bundles = array_keys($display->options['filters']['type']['value'] ?? []);
          }
          else {
            $target_bundles = array_keys($field_settings['handler_settings']['target_bundles'] ?? []);
          }

          // Iterate each target bundle and update the reference id.
          foreach ($target_bundles as $target_bundle) {

            $migration_table = 'migrate_map_' . $target_entity . '_' . $target_bundle;

            // Check the database has the correct schema and update table.
            if ($this->dbconn->schema()->tableExists($migration_table)) {
              $this->updateEntityReferences($migration_table, $field);
            }
            else {
              $this->logger->warning("Migration map table missing for $target_entity:$target_bundle");
            }
          }
        }
      }
    }
  }

  /**
   * Updates entity reference field targets from their D7 to the new D9 id.
   *
   * @param string $migration_table
   *   The migration mapping table to extract the destination node from.
   * @param \Drupal\field\Entity\FieldConfig $field
   *   The entity reference field.
   */
  private function updateEntityReferences(string $migration_table, FieldConfig $field) {
    // Check we have the D7 nid values in the migration mapping table.
    if ($this->dbconn->schema()->fieldExists($migration_table, 'sourceid2')) {
      $name = $field->getLabel();
      $field_table = 'node__' . $field->getName();
      $column = $field->getName() . '_target_id';

      // Update the entity reference target id with the migration map
      // destination id by matching the entity reference target id to the D7
      // id in the mapping table.
      $this->dbconn->query("UPDATE $migration_table AS mt, $field_table AS ft SET ft.$column = mt.destid1 WHERE ft.$column = mt.sourceid2");
      $this->logger->info("Updated target ids for $name");
    }
    else {
      $this->logger->warning("sourceid2 column missing from $migration_table, unable to lookup D7 nids.");
    }
  }

}
