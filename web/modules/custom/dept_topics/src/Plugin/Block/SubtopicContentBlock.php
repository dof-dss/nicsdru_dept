<?php

namespace Drupal\dept_topics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_topics\ContentSubTopics;
use Drupal\dept_topics\ContentTopics;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a subtopic content block.
 *
 * @Block(
 *   id = "dept_topics_subtopic_content_block",
 *   admin_label = @Translation("Subtopic content block"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class SubtopicContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The dept_topics.subtopics service.
   *
   * @var \Drupal\dept_topics\ContentSubTopics
   */
  protected $subtopics;

  /**
   * The dept_topics.topics service.
   *
   * @var \Drupal\dept_topics\ContentTopics
   */
  protected $topics;

  /**
   * Constructs a new SubtopicContentBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dept_topics\ContentSubTopics $subtopics
   *   The dept_topics.subtopics service.
   * @param \Drupal\dept_topics\ContentTopics $topics
   *   The dept_topics.topics service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContentSubTopics $subtopics, ContentTopics $topics) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->subtopics = $subtopics;
    $this->topics = $topics;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dept_topics.subtopics'),
      $container->get('dept_topics.topics')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');

    if (empty($node->id()) || $node->getType() !== 'subtopic') {
      return [];
    }

    $content = $this->subtopics->getSubtopicContent($node->id());

    $build['content'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $content,
    ];

    return $build;
  }

}
