<?php

namespace Drupal\revision_delete_tools\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;

/**
 * A Queue Worker that removes revisions for a given entity.
 *
 * @QueueWorker(
 *   id = "remove_revisions",
 *   title = @Translation("Remove entity revisions"),
 *   cron = {"time" = 60}
 * )
 */
final class RemoveRevisions extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The number of revisions to delete in a single chunk.
   */
  private const int REVISION_CHUNK_SIZE = 500;

  /**
   * The default number of revisions to keep.
   */
  public const int REVISIONS_TO_KEEP = 3;

  /**
   * Constructs a new RemoveRevisions instance.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the queue worker.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger factory service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('revision_delete_tools'),
    );
  }

  /**
   * Processes one queue item: deletes old revisions for one entity.
   */
  public function processItem(mixed $data): void {
    $entityType = $data['entityType'] ?? NULL;
    $entityId = $data['entityId'] ?? NULL;
    $keep = $data['keep'] ?? self::REVISIONS_TO_KEEP;

    if (!$entityType || !$entityId) {
      $this->logger->error('Missing queue data: entityType or entityId not set.');
      return;
    }

    $storage = $this->entityTypeManager->getStorage($entityType);
    if (!$storage instanceof RevisionableStorageInterface) {
      $this->logger->warning('Entity type @type is not revisionable.', ['@type' => $entityType]);
      return;
    }

    $entity = $storage->load($entityId);
    if (!$entity instanceof EntityInterface) {
      $this->logger->notice('Could not load entity @type:@id.', ['@type' => $entityType, '@id' => $entityId]);
      return;
    }

    if (!$entity->getEntityType()->isRevisionable()) {
      $this->logger->warning('Entity type @type claims not to be revisionable.', ['@type' => $entityType]);
      return;
    }

    $this->deleteRevisions($entity, $keep);
  }

  /**
   * Deletes all revisions for an entity, respecting the "keep" count.
   */
  protected function deleteRevisions(EntityInterface $entity, int $keep): void {
    if ($entity instanceof TranslatableInterface) {
      foreach ($entity->getTranslationLanguages() as $lang) {
        $this->deleteRevisionsByChunk($entity, $lang->getId(), $keep);
      }
    }
    else {
      $this->deleteRevisionsByChunk($entity, NULL, $keep);
    }
  }

  /**
   * Deletes revisions in chunks, keeping the most recent $keep revisions.
   */
  protected function deleteRevisionsByChunk(EntityInterface $entity, ?string $langcode, int $keep): void {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    if (!$storage instanceof RevisionableStorageInterface) {
      return;
    }

    $total = $this->baseRevisionQuery($storage, $entity, $langcode)->count()->execute();
    $remaining = $total - $keep;
    if ($remaining <= 0) {
      return;
    }

    $revisionKey = $entity->getEntityType()->getKey('revision');
    // Query all revisions for the entity, sorted newest-first, then skip
    // $keep to preserve the most recent and delete everything else.
    $query = $this->baseRevisionQuery($storage, $entity, $langcode)
      ->sort($revisionKey, 'DESC')
      ->range($keep, $remaining);

    $revisionIds = array_keys($query->execute());
    $chunks = array_chunk($revisionIds, self::REVISION_CHUNK_SIZE);

    foreach ($chunks as $chunk) {
      foreach ($chunk as $vid) {
        $storage->deleteRevision($vid);
      }
    }
  }

  /**
   * Base query for retrieving all revisions for an entity/language.
   */
  protected function baseRevisionQuery(RevisionableStorageInterface $storage, EntityInterface $entity, ?string $langcode): QueryInterface {
    $idKey = $entity->getEntityType()->getKey('id');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($idKey, $entity->id());

    if ($langcode) {
      // Entities like profile don't have a langcode key.
      if ($langcodeKey = $entity->getEntityType()->getKey('langcode')) {
        $query->condition($langcodeKey, $langcode);
      }
    }

    return $query;
  }

}
