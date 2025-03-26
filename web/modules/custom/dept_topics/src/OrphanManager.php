<?php

declare(strict_types=1);

namespace Drupal\dept_topics;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_topics\TopicManager;
use Drupal\node\NodeInterface;

/**
 * @todo Add class description.
 */
final class OrphanManager {

  /**
   * Constructs an OrphanManager object.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}


  public function processNode(NodeInterface $topic): void {

    if ($topic->bundle() !== 'topic' || $topic->bundle() == 'subtopic') {
      return;
    }

    $topic_childen = $topic->get('field_topic_content');

    foreach ($topic_childen as $child) {

    }

  }

  protected function addOrphan() {

  }

  protected function removeOrphan() {

  }

}
