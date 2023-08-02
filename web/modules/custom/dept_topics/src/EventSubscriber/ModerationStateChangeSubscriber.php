<?php

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\dept_topics\TopicManager;
use Drupal\origins_workflow\Event\ModerationStateChangeEvent;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsEvents;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Departmental sites: topics event subscriber.
 */
class ModerationStateChangeSubscriber implements EventSubscriberInterface {

  /**
   * The Topic Manager service.
   *
   * @var \Drupal\dept_topics\TopicManager
   */
  protected $topicManager;

  /**
   * Constructs a ModerationStateChangeSubscriber object.
   *
   * @param \Drupal\dept_topics\TopicManager $topic_manager
   *   The Topic Manager service.
   */
  public function __construct(TopicManager $topic_manager) {
    $this->topicManager = $topic_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ModerationStateChangeEvent::CHANGE => ['onModerationStateChange'],
      ScheduledTransitionsEvents::NEW_REVISION => ['newRevision'],
    ];
  }

  /**
   * Updates Topic child content references when moderation state is changed via Origins Workflow.
   *
   * @param \Drupal\origins_workflow\Event\ModerationStateChangeEvent $event
   *   The Origins Workflow moderation change event.
   */
  public function onModerationStateChange(ModerationStateChangeEvent $event) {
    if ($event->isPublished()) {
      $this->topicManager->updateChildOnTopics($event->getEntity());
    }
    elseif ($event->isArchived()) {
      $this->topicManager->removeChildFromTopics($event->getEntity());
    }
  }

  /**
   * Updates Topic child content references when moderation state is changed via Scheduled Transitions.
   *
   * @param ScheduledTransitionsNewRevisionEvent $event
   */
  public function newRevision(ScheduledTransitionsNewRevisionEvent $event) {
    $scheduledTransition = $event->getScheduledTransition();
    $entity = $scheduledTransition->getEntity();
    $revision = $event->getNewRevision();
    // TODO: Update topics when scheduled revisions are working.
  }

}
