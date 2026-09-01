<?php

declare(strict_types=1);

namespace Drupal\dept_topics;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Manages orphaned content.
 */
final class OrphanManager {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $orphanEntityStorage;

  /**
   * Constructs an OrphanManager object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TopicManager $topicManager,
  ) {
    $this->orphanEntityStorage = $this->entityTypeManager->getStorage('topics_orphaned_content');
  }

  /**
   * Process a list of nids that have been added or removed from a topic.
   *
   * @param array $nids
   *   Array of topic child content nids to process.
   *
   * @param \Drupal\node\NodeInterface|null $parent
   *   Optional parent node that the nids are associated with.
   */
  public function processTopicContents(array $nids, NodeInterface|Null $parent = NULL): void {
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
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which the orphan record should be created.
   * @param \Drupal\node\NodeInterface|null $parent
   *   The parent node of the orphaned node.
   */
  public function addOrphan(NodeInterface $node, NodeInterface|null $parent = NULL): void {
    $orphan = $this->orphanEntityStorage->loadByProperties(
      ['orphan' => $node->id()]
    );

    if (!empty($orphan)) {
      return;
    }

    $orphan = $this->orphanEntityStorage->create([
      'label' => $node->label(),
      'orphan' => $node->id(),
      'orphan_type' => $node->bundle(),
      'former_parent' => empty($parent) ? 'unknown' : $parent->id(),
      'department' => $node->get('field_domain_source')->getValue(),
      'uid' => \Drupal::currentUser()->id(),
      'created' => time(),
    ]);

    $this->orphanEntityStorage->save($orphan);
  }

  /**
   * Removes the orphan entity record for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which the orphan record should be removed.
   */
  public function removeOrphan(NodeInterface $node): void {
    $orphan = $this->orphanEntityStorage->loadByProperties(
      ['orphan' => $node->id()]
    );

    if (empty($orphan)) {
      return;
    }

    $this->orphanEntityStorage->delete($orphan);
  }

}
