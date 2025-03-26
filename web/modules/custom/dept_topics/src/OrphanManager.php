<?php

declare(strict_types=1);

namespace Drupal\dept_topics;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * @todo Add class description.
 */
final class OrphanManager {

  protected EntityStorageInterface $orphanEntityStorage;

  /**
   * Constructs an OrphanManager object.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->orphanEntityStorage = $this->entityTypeManager->getStorage('topics_orphaned_content');
  }


  public function processNode(NodeInterface $node): void {

    if ($node->bundle() === 'topic' || $node->bundle() === 'subtopic') {
      $topic_childen = $node->get('field_topic_content');
    }

    $topic_child_bundles = $this->topicManager->getTopicChildNodeTypes();

    if (in_array($node->bundle(), $topic_child_bundles)) {
      if (count($this->topicManager->getParentNodes($node)) == 0) {

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
  protected function addOrphan(NodeInterface $node, NodeInterface $parent): void {
    $orphan = $this->orphanEntityStorage->loadByProperties(
      ['orphan' => $node->id(),]
    );

    if (!empty($orphan)) {
      return;
    }

    $orphan = $this->orphanEntityStorage->create([
      'label' => $node->label(),
      'orphan' => $node->id(),
      'orphan_type' => $node->bundle(),
      'former_parent' => $parent->id(),
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
   *  The node for which the orphan record should be removed.
   */
  protected function removeOrphan(NodeInterface $node): void {
    $orphan = $this->orphanEntityStorage->loadByProperties(
      ['orphan' => $node->id(),]
    );

    if (empty($orphan)) {
      return;
    }

    $this->orphanEntityStorage->delete($orphan);
  }

}
