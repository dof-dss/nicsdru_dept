<?php

declare(strict_types=1);

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\dept_topics\TopicManager;
use Drupal\dept_topics\UiMessages;
use Drupal\domain_path_pathauto\DomainPathautoGenerator;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\facets\Exception\Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Entity event subscriber for processing topic entities.
 */
final class TopicsEntityEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TopicsEntityEventSubscriber object.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPathautoGenerator $domainPathautoGenerator,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Entity insert and update event handler.
   */
  public function onEntityInsertOrUpdate(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->isTopic($entity)) {
      return;
    }

    // Save child order to all Topic revisions (only if the order has changed).
    $ordered_nids = array_column($entity->get('field_topic_content')->getValue(), 'target_id');
    $this->topicManager->reorderChildren($entity, $ordered_nids);

    // Resolves an issue that prevented the 'Topics' field from including a
    // newly created topic when adding child content via the moderation sidebar.
    $domain_source = $entity->get('field_domain_source')->getValue();
    $dept_id = $domain_source[0]['target_id'];
    Cache::invalidateTags(['topics_field:' . $dept_id]);
    Cache::invalidateTags([$dept_id . '_topics']);
  }

  /**
   * Entity update event handler.
   */
  public function onEntityUpdate(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->isTopic($entity) || !$entity->isDefaultRevision()) {
      return;
    }

    // Ignore the node edit form as submit handles path alias.
    if ($this->routeMatch->getRouteName() === 'entity.node.edit_form') {
      return;
    }

    // Fetch all of the Domain aliases for this entity.
    $domain_aliases = $this->entityTypeManager->getStorage('domain_path')->loadByProperties(['source' => '/node/' . $entity->id()]);

    // Iterate each alias, generate the new alias and save.
    foreach ($domain_aliases as $domain_alias) {
      $domain_id = $domain_alias->getDomainId();

      // Skip if Domain pathauto is not enabled for this domain.
      if (!$this->domainPathautoGenerator->domainPathPathautoGenerationIsEnabled($entity, $domain_id)) {
        continue;
      }

      $new_alias = $this->domainPathautoGenerator->createEntityAlias($entity, 'return', $domain_id);
      if (!empty($new_alias) && $new_alias !== $domain_alias->getAlias()) {
        $domain_alias->set('alias', $new_alias)->save();
      }
    }
  }

  /**
   * Entity delete event handler.
   */
  public function onEntityDelete(EntityEvent $event): void {
    /* @var ContentEntityInterface $entity */
    $entity = $event->getEntity();

    if (!$this->isTopic($entity)) {
      return;
    }

    // Prevent deletion of topics if it has any active child content.
    // Adding this in addition to the frontend warning to provide coverage
    // when using the CLI (drush) etc.
    if ($this->topicManager->topicHasActiveChildren($entity)) {
      throw new Exception(UiMessages::deleteBlockedActiveChildren($entity->bundle(), $entity->label())->render());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['onEntityInsertOrUpdate'],
      EntityEventType::UPDATE => [['onEntityInsertOrUpdate'], ['onEntityUpdate']],
      EntityEventType::DELETE => ['onEntityDelete', 100],
    ];
  }

  /**
   * Determine if an entity is a valid Topic type based on bundle ID.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   True if valid topic bundle, otherwise false.
   */
  protected function isTopic(EntityInterface $entity): bool {
    return $entity instanceof ContentEntityInterface && in_array($entity->bundle(), ['topic', 'subtopic']);
  }

}
