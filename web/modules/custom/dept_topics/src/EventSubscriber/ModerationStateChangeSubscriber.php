<?php

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\dept_topics\TopicManager;
use Drupal\origins_workflow\Event\ModerationStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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
   * @param \Drupal\dept_topics\TopicManager $manager
   *   The topic.manager service.
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
    ];
  }

  public function onModerationStateChange(ModerationStateChangeEvent $event) {
    $state = $event->getState();

    if ($state === 'published') {
      $this->topicManager->updateChildOnTopics($event->getEntity());
    } elseif ($state === 'archived') {
      $this->topicManager->removeChildFromTopics($event->getEntity());
    }
  }

}
