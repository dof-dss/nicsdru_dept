<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
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
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('renderer'),
    );
  }

  /**
   * Main report method.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Topic node to generate the report for.
   */
  public function report(NodeInterface $node) {
    $build['content']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h4',
      '#value' => $this->t('Report for @title', ['@title' => $node->label()]),
    ];

    $build['content']['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'small',
      '#value' => 'Hover over a title to display the content type and author.',
      '#suffix' => '<hr>',
    ];

    $this->parentTopics($node);
    $tree = $this->buildTree($this->topics);

    $build['content']['tree'] = $this->renderTree($tree);
    $build['#attached']['library'][] = 'dept_topics/topic_structure_report';

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
      $title = $item['status'] ? $item['title'] : $item['title'] . ' <span class="unpublished">(unpublished)</span>';
      $branch = ['#markup' => '<span title="' . $item['bundle'] . ' created by ' . $item['author'] . '">' . $title . '</span>'];

      // Add children render array to branch.
      if (!empty($item['children'])) {
        $children = $this->renderTree($item['children']);
        $branch['#suffix'] = $this->renderer->renderRoot($children);
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
   *
   * @param \Drupal\node\NodeInterface $topic
   *   The topic to generate the hierarchy for.
   */
  public function parentTopics(NodeInterface $topic) {
    $this->topics[] = [
      'id' => $topic->id(),
      'title' => $topic->label(),
      'parent' => '0',
      'status' => $topic->isPublished(),
      'bundle' => ucfirst($topic->bundle()),
      'author' => $topic->getOwner()->getDisplayName(),
    ];

    $this->subtopics($topic);
  }

  /**
   * Extract topic children and adds to the hierarchy data structure.
   *
   * @param \Drupal\node\NodeInterface $parent
   *   Parent topic to extract child content from.
   */
  public function subtopics(NodeInterface $parent) {
    $child_content = $parent->get('field_topic_content')->referencedEntities();

    /** @var \Drupal\node\NodeInterface $child */
    foreach ($child_content as $child) {

      if ($child->bundle() !== 'publication') {
        $this->topics[] = [
          'id' => $child->id(),
          'title' => $child->label(),
          'parent' => $parent->id(),
          'status' => $child->isPublished(),
          'bundle' => ucfirst($child->bundle()),
          'author' => $child->getOwner()->getDisplayName(),
        ];

        if ($child->bundle() === 'subtopic') {
          $this->subtopics($child);
        }
      }
    }
  }

}
