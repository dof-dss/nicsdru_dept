<?php

declare(strict_types=1);

namespace Drupal\dept_homepage\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\origins_workflow\Event\ModerationStateChangeEvent;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsEvents;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @todo Handles node status changes.
 */
final class NodeStatusSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ModerationStateChangeEvent::CHANGE => ['onModerationStateChange'],
      ScheduledTransitionsEvents::NEW_REVISION => ['newRevision', 1011],
    ];
  }

  /**
   * Updates homepage featured content when moderation state is changed via Origins Workflow.
   *
   * @param \Drupal\origins_workflow\Event\ModerationStateChangeEvent $event
   *   The Origins Workflow moderation change event.
   */
  public function onModerationStateChange(ModerationStateChangeEvent $event) {
    if ($event->isPublished() || $event->isArchived()) {
      $this->updateDepartmentFeatured($event->getEntity());
    }
  }

  /**
   * Updates homepage featured content when moderation state is changed via Scheduled Transitions.
   *
   * @param \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event
   *   Scheduled Transition event.
   */
  public function newRevision(ScheduledTransitionsNewRevisionEvent $event) {
    $scheduledTransition = $event->getScheduledTransition();
    $state = $scheduledTransition->getState();

    if ($state == 'published' || $state == 'archived') {
      $this->updateDepartmentFeatured($scheduledTransition->getEntity());
    }
  }

  protected function updateDepartmentFeatured(EntityInterface $entity) {
    // Clear node domain/dept and nigov featured cache.
    $domain = $entity->get('domain_source')->getString();
  }

}
