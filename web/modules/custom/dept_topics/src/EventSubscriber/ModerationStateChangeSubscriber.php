<?php

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
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
      ScheduledTransitionsEvents::NEW_REVISION => ['newRevision', 1010],
    ];
  }

  /**
   * Updates Topic child content references when moderation state is changed via Origins Workflow.
   *
   * @param \Drupal\origins_workflow\Event\ModerationStateChangeEvent $event
   *   The Origins Workflow moderation change event.
   */
  public function onModerationStateChange(ModerationStateChangeEvent $event) {
    /* @var $entity ContentEntityInterface */
    $entity = $event->getEntity();

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    $state = $event->getState();

    if ($state == 'published') {
      $this->topicManager->processChild($entity);
    }

    if ($state == 'archived') {
      $this->topicManager->archiveChild($entity);
    }
  }

  /**
   * Updates Topic child content references when moderation state is changed via Scheduled Transitions.
   *
   * @param \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event
   *   Scheduled Transition event.
   */
  public function newRevision(ScheduledTransitionsNewRevisionEvent $event) {
    /* @var $entity ContentEntityInterface */
    $scheduledTransition = $event->getScheduledTransition();
    $entity = $scheduledTransition->getEntity();

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    $state = $scheduledTransition->getState();

    if ($state == 'published') {
      $this->topicManager->processChild($entity);
    }

    if ($state == 'archived') {
      $this->topicManager->archiveChild($entity);
    }
  }

}
