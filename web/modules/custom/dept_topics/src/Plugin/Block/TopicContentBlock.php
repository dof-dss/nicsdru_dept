<?php

namespace Drupal\dept_topics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_topics\ContentSubTopics;
use Drupal\dept_topics\ContentTopics;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a topic content block.
 *
 * @Block(
 *   id = "dept_topics_topic_content_block",
 *   admin_label = @Translation("Topic content block"),
 *   category = @Translation("Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class TopicContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * Constructs a new TopicContentBlock instance.
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

    if (empty($node->id()) || $node->getType() !== 'topic') {
      return [];
    }

    $build = [];
    $subtopics = $this->topics->getSubtopicsForTopic($node->id());

    if (!empty($subtopics)) {

      $build = [
        '#title' => $node->getTitle(),
        '#attributes' => [
          'class' => ['subtopics'],
        ],
        'items' => [],
      ];

      foreach ($subtopics as $subtopic_id => $subtopic) {

        $subtopic_content = $this->subtopics->getSubtopicContent($subtopic_id);

        // Prefix all included content with 'node:' to form suitable cache tags.
        $cache_tags = preg_filter('/^/', 'node:', array_keys($subtopic_content));
        // Add the content stack view tag so that when draggableviews expires
        // the view tags relating to any changes, that we regenerate any
        // html relating to this render element as required.
        $cache_tags[] = 'config:views.view.content_stacks';
        // Add cache tag for the related subtopic node id.
        $cache_tags[] = 'node:' . $subtopic_id;

        $build['items'][] = [
          '#theme' => 'subtopic_content_list__item',
          '#title' => $subtopic['title'],
          '#title_link' => Link::createFromRoute(
            $subtopic['title'],
            'entity.node.canonical',
            ['node' => $subtopic_id],
          ),
          '#content_summary' => [
            '#markup' => $subtopic['summary'],
          ],
          '#content_links' => $subtopic_content,
          '#read_more_link' => Link::createFromRoute(
            t('Read more'),
            'entity.node.canonical',
            ['node' => $subtopic_id],
          ),
          '#cache' => [
            'keys' => ['subtopic_stack:' . $subtopic_id],
            'tags' => $cache_tags,
          ]
        ];
      }
    }

    return $build;
  }

}
