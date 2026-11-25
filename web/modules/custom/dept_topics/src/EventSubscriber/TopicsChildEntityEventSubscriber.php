<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\dept_topics\TopicManager;
use Drupal\dept_topics\UiMessages;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Entity event subscriber for processing topic child entities.
 */
final class TopicsChildEntityEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TopicsChildEntityEventSubscriber object.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
    private readonly ModerationInformationInterface $moderationInformation,
    private readonly MessengerInterface $messenger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Entity insert event handler.
   */
  public function onEntityInsert(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    if ($entity->get('moderation_state')->getString() !== 'archived') {
      $topics = $entity->get('field_site_topics')->referencedEntities();
      foreach ($topics as $topic) {
        $this->topicManager->addChild($entity, $topic);

        if (!$topic->isPublished()) {
          $this->messenger->addWarning(UiMessages::assignedToUnpublishedTopic($topic->label()));
        }
      }
    }
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getEntity();
    $current_topics_ids = [];

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    foreach ($entity->get('field_site_topics')->referencedEntities() as $topic) {
      $current_topics_ids[] = $topic->id();

      if (!$topic->isPublished()) {
        $this->messenger->addWarning(UiMessages::assignedToUnpublishedTopic($topic->label()));
      }
    }

    $is_published = $this->moderationInformation->isDefaultRevisionPublished($entity);
    $moderation_state = $entity->get('moderation_state')->getString();

    switch ($moderation_state) {
      case 'archived':
        $this->topicManager->archiveChild($entity);
        break;

      case 'draft':
      case 'needs_review':
        if ($is_published) {
          $published_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
          $published_topics_ids = array_column($published_entity->get('field_site_topics')->getValue(), 'target_id');
          sort($current_topics_ids);
          sort($published_topics_ids);

          if ($current_topics_ids !== $published_topics_ids) {
            $this->messenger->addMessage(UiMessages::topicChangesPendingPublish());
          }
        }
        else {
          $this->topicManager->processChild($entity);
        }
        break;

      default:
        $this->topicManager->processChild($entity);
        break;
    }
  }

  /**
   * Entity delete event handler.
   */
  public function onEntityDelete(EntityEvent $event): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    // Remove deleted child from topic references.
    $topics = $entity->get('field_site_topics')->referencedEntities();

    foreach ($topics as $topic) {
      $this->topicManager->removeChild($entity, $topic);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['onEntityInsert'],
      EntityEventType::UPDATE => ['onEntityUpdate'],
      EntityEventType::DELETE => ['onEntityDelete', 100],
    ];
  }

}
