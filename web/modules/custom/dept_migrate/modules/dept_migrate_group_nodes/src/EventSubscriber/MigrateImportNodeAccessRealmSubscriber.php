<?php

namespace Drupal\dept_migrate_group_nodes\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Migration event subscriber to set the correct realm value(s) in the
 * node_access table, to ensure compatibility with group module + domain_group.
 */
class MigrateImportNodeAccessRealmSubscriber implements EventSubscriberInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   */
  public function __construct(LoggerChannelFactory $logger, Connection $connection) {
    $this->logger = $logger->get('dept_migrate_group_nodes');
    $this->dbconn = $connection;
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onMigratePostRowSave'];
    return $events;
  }

  /**
   * Ensures imported nodes have the correct node_access table
   * values based on realm and domain_id.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The event object.
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    $event_id = $event->getMigration()->getBaseId();

    if (preg_match('/^node_/', $event_id) === FALSE) {
      return;
    }

    $idvalues = $event->getDestinationIdValues();
    $sourcevalues = $event->getRow()->getSourceIdValues();
    $domains = $sourcevalues['domains'];

    if (empty($idvalues) || empty($domains)) {
      $this->logger->warning('Empty source id or domain values.');
      return;
    }

    // Break up domains by hypen for multi-value things.
    $domains = explode('-', $domains);

    $d9nid = $idvalues[0];
    $this->dbconn->query("DELETE FROM {node_access} WHERE nid = :nid", [':nid' => $d9nid]);

    foreach ($domains as $d7_domain_id) {
      $d9_domain_id = $this->d7DomainIdToD9DomainId($d7_domain_id);

      if (!empty($d9_domain_id)) {
        $this->dbconn->insert('node_access')
          ->fields([
            'nid' => $d9nid,
            'langcode' => 'und',
            'fallback' => 1,
            'gid' => $d9_domain_id,
            'realm' => 'group_domain_id',
            'grant_view' => 1,
            'grant_update' => 0,
            'grant_delete' => 0
          ])->execute();
      }
      else {
        $this->logger->warning('Empty D9 domain id for node ' . $d9nid);
      }
    }
  }

  /**
   * Convenience function to convert between domain id values.
   *
   * D9 stores randomised integer values for domain ids. We could
   * look them up in the config files, but as they're essentially
   * static once set up, and the ETGRM sprocs build a temporary table
   * to do much the same thing, we use a function to do the conversion.
   *
   * @param int $d7_domain_id
   *   A Drupal 7 domain id.
   * @return int
   *   The D9 domain ID (based on config file contents).
   */
  private function d7DomainIdToD9DomainId(int $d7_domain_id) {
    $d9_domain_id = 0;

    switch ($d7_domain_id) {
      case 1:
        $d9_domain_id = 4567776;
        break;

      case 2:
        $d9_domain_id = 10261667;
        break;

      case 3:
        $d9_domain_id = 2853218;
        break;

      case 4:
        $d9_domain_id = 3070245;
        break;

      case 5:
        $d9_domain_id = 10077412;
        break;

      case 6:
        $d9_domain_id = 4252327;
        break;

      case 7:
        $d9_domain_id = 16252774;
        break;

      case 8:
        $d9_domain_id = 4866601;
        break;

      case 9:
        $d9_domain_id = 16605160;
        break;

      case 10:
        $d9_domain_id = 8363580;
        break;
    }

    return $d9_domain_id;
  }

}
