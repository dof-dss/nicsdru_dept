<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dept_topics\TopicManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Departmental sites: topics routes.
 */
final class TopicStructureReportController extends ControllerBase {


  /**
   * Topic hierarchy structure.
   * @var array
   */
  protected $topics = [];

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('topic.manager'),
    );
  }

  /**
   * Main report method.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Topic node to generate the report for.
   */
  public function report(NodeInterface $node) {
    $build['content']['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'h4',
      '#value' => $this->t('Report for @title', ['@title' => $node->label()]),
    ];

    $this->parentTopics($node);
    $tree = $this->buildTree($this->topics);

    $build['content']['tree'] = $this->renderTree($tree);

    return $build;
  }

  /**
   * Generate nested tree structure from hierarchy data.
   *
   * @param array $items
   *   Hierarchy items.
   *
   * @param int $parent
   *   Parent item ID.
   */
  protected function buildTree(array $items, $parent = 0) {
    $branch = [];
    foreach ($items as $item) {
      if ($item['parent'] == $parent) {
        $children = $this->buildTree($items, $item['id']);
        $item['children'] = $children ?? NULL;
        $branch[] = $item;
      }
    }
    return $branch;
  }

  /**
   * Generated render array of topic tree structure.
   *
   * @param array $tree
   *   Tree structure to generate the markup for.
   */
  protected function renderTree(array $tree) {
    $items = [];

    foreach ($tree as $item) {
      $branch = ['#markup' => $item['title']];

      // Add children render array to branch.
      if (!empty($item['children'])) {
        $children = $this->renderTree($item['children']);
        $branch['#suffix'] = \Drupal::service('renderer')->renderRoot($children);
      }
      $items[] = $branch;
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

  /**
   * Takes a topic and generates the hierarchy data structure.
   */
  public function parentTopics($topic) {
    $this->topics[] = [
      'id' => $topic->id(),
      'title' => $topic->label(),
      'parent' => '0',
    ];

    $this->subtopics($topic);
  }

  /**
   * Extracts topic children and adds to the hierarchy data structure.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   Parent topic to extract child content from.
   */
  public function subtopics(NodeInterface $parent) {
    $child_content = $parent->get('field_topic_content')->referencedEntities();

    foreach ($child_content as $child) {

      if ($child->bundle() !== 'publication') {
        $this->topics[] = [
          'id' => $child->id(),
          'title' => $child->label(),
          'parent' => $parent->id(),
        ];

        if ($child->bundle() === 'subtopic') {
          $this->subtopics($child);
        }
      }
    }
  }

}
