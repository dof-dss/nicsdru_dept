<?php

namespace Drupal\dept_migrate_files\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\file\FileInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Post migration subscriber to correct managed file import properties.
 */
final class PostMigrationFileImportTidy implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   Drupal logger.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Database\Connection $d7Connection
   *   D7 database connection.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookupManager
   *   Migrate lookup manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactory $loggerFactory,
    protected Connection $connection,
    protected Connection $d7Connection,
    protected MigrateUuidLookupManager $lookupManager,
  ) {
    $this->logger = $loggerFactory->get('dept_migrate');
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

    if ($event_id === 'd7_file' || $event_id === 'd7_file_private') {
      $this->logger->notice("Fixing missing file properties for " . $event_id);

      // Find all records in file_managed with NULL filename.
      $results = $this->connection->query("SELECT f.*, m.*
        FROM {file_managed} f
        JOIN {migrate_map_$event_id} m ON m.destid1 = f.fid
        WHERE f.filename IS NULL")->fetchAll();

      foreach ($results as $row) {
        $d7_metadata = $this->lookupManager->lookupBySourceFileUuid([$row->sourceid1]);
        $d7_fid = key($d7_metadata);

        if (!empty($d7_metadata)) {
          $d7_metadata = reset($d7_metadata);
          $d7_file = $this->d7Connection->query('SELECT * from {file_managed} WHERE fid = :fid', [
            ':fid' => $d7_fid,
          ])->fetchAssoc();

          $file = $this->entityTypeManager->getStorage('file')->load($d7_metadata['id']);

          if ($file instanceof FileInterface) {
            $file->setFilename($d7_file['filename']);
            $file->setFileUri($d7_file['uri']);
            $file->setMimeType($d7_file['filemime']);
            $file->setSize($d7_file['filesize']);

            $file->save();

            $this->logger->info("Synced file values for fid " . $file->id() . " - " . $file->label());
          }
        }
        else {
          // If not, log it as a warning.
          $this->logger->warning("No D7 file found for " . $row->filename . ' D7 fid ' . $row->fid);
        }
      }

    }
  }

}
