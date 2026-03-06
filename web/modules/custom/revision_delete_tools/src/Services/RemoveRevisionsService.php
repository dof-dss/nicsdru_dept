<?php

namespace Drupal\revision_delete_tools\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\revision_delete_tools\Plugin\QueueWorker\RemoveRevisions;

/**
 * Service for queuing and managing entity revision deletions.
 */
final class RemoveRevisionsService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly Connection $database,
  ) {}

  /**
   * Queues revisions for entities by type.
   */
  public function queueRevisionsByType(string $entityType, int $keep = RemoveRevisions::REVISIONS_TO_KEEP): void {
    foreach ($this->getBundleNames($entityType) as $bundleName) {
      $entityIds = $this->getEntityIds($entityType, $bundleName, $keep);
      $this->queueEntities($entityType, $entityIds, $keep);
    }
  }

  /**
   * Queues revisions for entities by bundle.
   */
  public function queueRevisionsByBundle(string $entityType, string $bundle, int $keep = RemoveRevisions::REVISIONS_TO_KEEP): void {
    $entityIds = $this->getEntityIds($entityType, $bundle, $keep);
    $this->queueEntities($entityType, $entityIds, $keep);
  }

  /**
   * Queues revisions for entities by bundle.
   */
  public function queueRevisionsByEntityId(string $entityType, string $entityId, int $keep = RemoveRevisions::REVISIONS_TO_KEEP): void {
    $this->queueFactory->get('remove_revisions')->createItem([
      'entityId' => $entityId,
      'entityType' => $entityType,
      'keep' => $keep,
    ]);
  }

  /**
   * Returns entity IDs for a given entity type and optional bundle.
   *
   * Only returns entities with more revisions than the given keep value.
   * Fast SQL implementation that avoids loading entities.
   */
  public function getEntityIds(string $entityType, ?string $bundle = NULL, int $keep = RemoveRevisions::REVISIONS_TO_KEEP): array {
    $storage = $this->entityTypeManager->getStorage($entityType);
    $definition = $storage->getEntityType();
    $baseTable = $definition->getBaseTable();
    $revisionTable = $definition->getRevisionTable();
    $revisionDataTable = $definition->getRevisionDataTable();
    $idKey = $definition->getKey('id');
    $revisionKey = $definition->getKey('revision');
    $bundleKey = $definition->getKey('bundle');
    $languageKey = $definition->getKey('langcode');

    if (!$revisionTable || !$idKey) {
      return [];
    }

    $query = $this->database->select($revisionDataTable ?? $revisionTable, 'r');
    $query->addField('r', $idKey);
    $query->groupBy("r.$idKey");
    if ($languageKey && $revisionDataTable) {
      // Grouping over languages only makes sense if a data table exists.
      $query->groupBy("r.$languageKey");
    }
    $query->having("COUNT(r.$revisionKey) > :min", [':min' => $keep]);

    if ($bundle !== NULL && $bundleKey) {
      $query->innerJoin($baseTable, 'b', "b.$idKey = r.$idKey");
      $query->condition("b.$bundleKey", $bundle);
    }

    $result = $query->execute()->fetchCol();

    return $result ?: [];
  }

  /**
   * Get the bundle names for a specific entity type.
   */
  public function getBundleNames(string $entityType): array {
    return array_keys($this->bundleInfo->getBundleInfo($entityType));
  }

  /**
   * Get the bundle names for a specific entity type.
   */
  public function getRevisionableEntityTypes(): array {
    $revisionEnabled = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if ($entityType->isRevisionable()) {
        $revisionEnabled[] = $entityTypeId;
      }
    }
    return $revisionEnabled;
  }

  /**
   * Queues revision deletion for multiple entity IDs.
   */
  private function queueEntities(string $entityType, array $entityIds, int $keep = RemoveRevisions::REVISIONS_TO_KEEP): void {
    foreach ($entityIds as $entityId) {
      $this->queueRevisionsByEntityId($entityType, $entityId, $keep);
    }
  }

}
