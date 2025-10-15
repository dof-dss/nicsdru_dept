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
    $this->processModerationState($event->getEntity(), $event->getState());
  }

  /**
   * Updates Topic child content references when moderation state is changed via Scheduled Transitions.
   *
   * @param \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event
   *   Scheduled Transition event.
   */
  public function newRevision(ScheduledTransitionsNewRevisionEvent $event) {
    $scheduledTransition = $event->getScheduledTransition();
    $this->processModerationState($scheduledTransition->getEntity(), $scheduledTransition->getState());
  }

  /**
   * Process an entity depending on the moderation state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   * @param string $state
   *   The moderation state of the entity.
   */
  protected function processModerationState($entity, $state) {
    if ($this->topicManager->isValidTopicChild($entity)) {
      if ($state == 'published') {
        $this->topicManager->processChild($entity);
      }
      elseif ($state == 'archived') {
        $this->topicManager->archiveChild($entity);
      }
    }
  }

}
