<?php

declare(strict_types=1);

namespace Drupal\dept_homepage\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\origins_workflow\Event\ModerationStateChangeEvent;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsEvents;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles node status changes for homepage content.
 */
final class NodeStatusSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $dbConn;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database_connection
   *   The database connection.
   */
  public function __construct(Connection $database_connection) {
    $this->dbConn = $database_connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ModerationStateChangeEvent::CHANGE => ['onModerationStateChange', 100],
      ScheduledTransitionsEvents::NEW_REVISION => ['newRevision', 1011],
    ];
  }

  /**
   * Handles processes when the moderation state is changed via Origins Workflow.
   *
   * @param \Drupal\origins_workflow\Event\ModerationStateChangeEvent $event
   *   The Origins Workflow moderation change event.
   */
  public function onModerationStateChange(ModerationStateChangeEvent $event): void {
    if ($event->isPublished() || $event->isArchived()) {
      $this->updateDepartmentFeatured($event->getEntity());
    }
  }

  /**
   * Handles processes when the moderation state is changed via Scheduled Transitions.
   *
   * @param \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event
   *   Scheduled Transition event.
   */
  public function newRevision(ScheduledTransitionsNewRevisionEvent $event): void {
    $scheduledTransition = $event->getScheduledTransition();
    $state = $scheduledTransition->getState();

    if ($state == 'published' || $state == 'archived') {
      $this->updateDepartmentFeatured($scheduledTransition->getEntity());
    }
  }

  /**
   * Clears the homepage featured cache block for a featured node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node currently undergoing a state change.
   */
  protected function updateDepartmentFeatured(EntityInterface $entity) {
    /** @var \Drupal\node\Entity\Node $entity */
    $domain = $entity->get('field_domain_source')->getString();

    if (empty($domain)) {
      return;
    }

    // Fetch a list of featured homepage node ID's for the Node (Entity) domain.
    $query = $this->dbConn->select('node__field_featured_content', 'fc');
    $query->join('node__field_domain_source', 'ds', 'fc.entity_id = ds.entity_id');
    $query
      ->fields('fc', ['field_featured_content_target_id'])
      ->condition('ds.bundle', 'featured_content_list')
      ->condition('ds.field_domain_source_target_id', $domain);

    $featured_nids = $query->execute()->fetchCol();

    // If the node (Entity) is a featured item, clear the cache.
    if (in_array($entity->id(), $featured_nids)) {
      Cache::invalidateTags(['homepage_featured:' . $domain]);
    }
  }

}
