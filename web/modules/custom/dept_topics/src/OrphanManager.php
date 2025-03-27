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

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
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

  /**
   * Process a list of child content associated with a topic.
   *
   * @param \Drupal\node\NodeInterface $topic
   *   The parent topic node.
   * @param array $altered_children
   *   Array of added or removed children (NID's) from the topic content.
   */
  public function process(NodeInterface $topic, array $altered_children): void {

    if ($topic->bundle() !== 'topic' || $topic->bundle() !== 'subtopic') {
      return;
    }

    foreach ($altered_children as $child_id) {
      $child = $this->entityTypeManager->getStorage('node')->load($child_id);

      if (!empty($child)) {
        if ($child->hasField('field_site_topics')) {
          if (count($child->get('field_site_topics')->getValue()) > 0) {
            $this->removeOrphan($child);
          }
          else {
            $this->addOrphan($child, $topic);
          }
        }
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
      ['orphan' => $node->id()]
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
   *   The node for which the orphan record should be removed.
   */
  protected function removeOrphan(NodeInterface $node): void {
    $orphan = $this->orphanEntityStorage->loadByProperties(
      ['orphan' => $node->id()]
    );

    if (empty($orphan)) {
      return;
    }

    $this->orphanEntityStorage->delete($orphan);
  }

}
