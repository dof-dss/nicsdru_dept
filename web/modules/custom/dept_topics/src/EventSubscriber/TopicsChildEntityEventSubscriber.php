<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dept_topics\TopicManager;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Entity event subscriber for processing topic child entities.
 */
final class TopicsChildEntityEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  // Message for when adding/updating a child node and a chosen topic is unpublished.
  const string MESSAGE_ASSIGNED_TO_UNPUBLISHED_TOPIC = "This content is associated with an unpublished topic (%topic), so visitors have no way to reach it through that topic on the site.";

  // Message for when published child content has the topics changed but the child revision is in a non-published state.
  const string MESSAGE_PUBLISHED_CONTENT_TOPICS = "This content already has a published revision, and the Topics you've selected differ from that published version. The new Topics will not take effect until this revision is published.";

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
          \Drupal::messenger()->addWarning($this->t(self::MESSAGE_ASSIGNED_TO_UNPUBLISHED_TOPIC,
            ['%topic' => $topic->label()]));
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

    if (!$this->topicManager->isValidTopicChild($entity)) {
      return;
    }

    foreach ($entity->get('field_site_topics')->referencedEntities() as $topic) {
      $current_topics_ids[] = $topic->id();

      if (!$topic->isPublished()) {
        \Drupal::messenger()->addWarning($this->t(self::MESSAGE_ASSIGNED_TO_UNPUBLISHED_TOPIC,
          ['%topic' => $topic->label()]));
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
            $this->messenger->addMessage(self::MESSAGE_ASSIGNED_TO_UNPUBLISHED_TOPIC);
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
