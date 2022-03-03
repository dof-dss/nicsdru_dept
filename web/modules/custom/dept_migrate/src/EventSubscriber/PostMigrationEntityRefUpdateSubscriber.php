<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
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
          $field_table = 'node__' . $field->getName();
          $column = $field->getName() . '_target_id';
          $field_settings = $field->getSettings();

          // Determine the reference types the field targets.
          if ($field_settings['handler'] === 'views') {
            $view = Views::getView($field_settings['handler_settings']['view']['view_name']);
            $display = $view->getDisplay($field_settings['handler_settings']['view']['display_name']);
            $target_bundles = array_keys($display->options['filters']['type']['value']);
          } else {
            $target_bundles = array_keys($field_settings['handler_settings']['target_bundles']);
          }

          $target_entity = $field_settings['target_type'];

          // Iterate each target bundle and update the reference ID.
          foreach ($target_bundles as $target_bundle) {
            // TODO: Not all migrations follow this naming structure and some include 'd7' after 'map_'
            // Check the database has the correct schema and update table.
            if ($dbconn_default->schema()->tableExists($migration_table = 'migrate_map_' . $target_entity . '_' . $target_bundle)) {
              $this->updateEntityReferences($migration_table, $field_table);
            }
            elseif($dbconn_default->schema()->tableExists($migration_table = 'migrate_map_d7_' . $target_entity . '_' . $target_bundle)) {
              $this->updateEntityReferences($migration_table, $field_table);
            } else {
              $this->logger->notice("Migration map table missing for $target_entity:$target_bundle");
            }
          }
        }
      }
    }
  }

  private function updateEntityReferences($migration_table, $field_table) {
    $dbconn_default = Database::getConnection('default', 'default');

    if ($dbconn_default->schema()->fieldExists($migration_table, 'sourceid2')) {
      $dbconn_default->query("UPDATE $migration_table AS mt, $field_table AS ft SET ft.field_site_topics_target_id = mt.destid1 WHERE ft.field_site_topics_target_id = mt.sourceid2");
    } else {
      $this->logger->notice("sourceid2 column missing from $migration_table, unable to lookup D7 nids.");
    }
  }
}
