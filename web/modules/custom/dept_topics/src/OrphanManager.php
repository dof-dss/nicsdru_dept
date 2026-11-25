<?php

declare(strict_types=1);

namespace Drupal\dept_topics;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Manages orphaned content.
 */
final class OrphanManager {

  /**
   * Storage for orphan entities.
   */
  private EntityStorageInterface $orphanEntityStorage;

  /**
   * Constructs an OrphanManager object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TopicManager $topicManager,
    private readonly AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * Process a list of nids that have been added or removed from a topic.
   *
   * @param int[] $nids
   *   Array of topic child content nids to process.
   * @param \Drupal\node\NodeInterface|null $parent
   *   Optional parent node that the nids are associated with.
   */
  public function processTopicContents(array $nids, ?NodeInterface $parent = NULL): void {
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      if (count($this->topicManager->getParentNodes($node)) < 1) {
        $this->addOrphan($node, $parent);
      }
      else {
        $this->removeOrphan($node);
      }
    }
  }

  /**
   * Creates an orphan entity record for a given node.
   */
  public function addOrphan(NodeInterface $node, ?NodeInterface $parent = NULL): void {
    $orphan_storage = $this->entityTypeManager->getStorage('topics_orphaned_content');
    $existing = $orphan_storage->loadByProperties(['orphan' => $node->id()]);
    if (!empty($existing)) {
      return;
    }

    $orphan = $orphan_storage->create([
      'label' => $node->label(),
      'orphan' => $node->id(),
      'orphan_type' => $node->bundle(),
      'former_parent' => $parent?->id() ?? 'unknown',
      'department' => $node->get('field_domain_source')->getValue(),
      'uid' => $this->currentUser->id(),
      'created' => time(),
    ]);

    $orphan_storage->save($orphan);
  }

  /**
   * Removes the orphan entity record for a given node.
   */
  public function removeOrphan(NodeInterface $node): void {
    $orphan_storage = $this->entityTypeManager->getStorage('topics_orphaned_content');
    $orphans = $orphan_storage->loadByProperties(['orphan' => $node->id()]);
    if (empty($orphans)) {
      return;
    }

    $orphan_storage->delete($orphans);
  }

}
