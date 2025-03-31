<?php

declare(strict_types=1);

namespace Drupal\dept_topics;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

enum ContentAction {
  case ADDED;
  case REMOVED;
}

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
  ) {
    $this->orphanEntityStorage = $this->entityTypeManager->getStorage('topics_orphaned_content');
  }

  public function processTopicContents(array $nids, ContentAction $action, NodeInterface $parent = NULL): void {
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    if ($action == ContentAction::ADDED) {
      foreach ($nodes as $node) {
        $this->removeOrphan($node);
      }
    }
    else {
      foreach ($nodes as $node) {
        $this->addOrphan($node, $parent);
      }
    }
  }

  /**
   * Creates an orphan entity record for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which the orphan record should be created.
   * @param \Drupal\node\NodeInterface $parent
   *   The parent node of the orphaned node.
   */
  public function addOrphan(NodeInterface $node, NodeInterface $parent): void {
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
