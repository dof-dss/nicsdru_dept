<?php

namespace Drupal\dept_node\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\dept_topics\TopicManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a node banner block.
 *
 * @Block(
 *   id = "dept_node_node_banner",
 *   admin_label = @Translation("Node Banner"),
 *   category = @Translation("Departmental"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class NodeBannerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Topic Manager service.
   *
   * @var \Drupal\dept_topics\TopicManager
   */
  protected $topicManager;

  /**
   * The Entity type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new Node Banner Block instance.
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
   * @param \Drupal\dept_topics\TopicManager $topic_manager
   *   The Topic Manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity type Manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TopicManager $topic_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->topicManager = $topic_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('topic.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');
    $banner_bundles = $this->topicManager->getTopicChildNodeTypes();
    // Manually add 'topic' as this is not a topic child content type but has a banner.
    $banner_bundles['topic'] = 'topic';

    // Only display banners for bundles that belong to the topics' system.
    if (!array_key_exists($node->bundle(), $banner_bundles)) {
      return;
    }

    // If current node uses layout builder then return as the banner wll be in the layout.
    if ($node->hasField('layout_builder__layout') && !empty($node->get('layout_builder__layout')->getValue())) {
      return;
    }

    // If current node has a banner, return as it'll be displayed in the view mode or layout.
    if ($node->hasField('field_banner_image') && !$node->get('field_banner_image')->isEmpty()) {
      // Banner background image.
      $display = EntityViewDisplay::collectRenderDisplay($node, 'default')->getComponent('field_banner_image');
      $build['banner'] = $node->get('field_banner_image')->view($display);

      // Banner overlay image
      if (!$node->get('field_banner_image_overlay')->isEmpty()) {
        $display = EntityViewDisplay::collectRenderDisplay($node, 'default')->getComponent('field_banner_image_overlay');
        $build['banner_overlay'] = $node->get('field_banner_image_overlay')->view($display);
      }

      return $build;
    }

    // Fetch parents, if parents have banner use it.
    $parent_nids = array_keys($this->topicManager->getParentNodes($node->id()));
    $banner_link = '';

    $parent_nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($parent_nids);

    // Iterate each parent checking for a thin banner image.
    foreach ($parent_nodes as $parent_node) {
      if ($parent_node->hasField('field_banner_image_thin') && !$parent_node->get('field_banner_image_thin')->isEmpty()) {

        $display = EntityViewDisplay::collectRenderDisplay($parent_node, 'default')->getComponent('field_banner_image_thin');
        $build['banner'] = $parent_node->get('field_banner_image_thin')->view($display);

        $build['link'] = Url::fromRoute('entity.node.canonical', ['node' => $parent_node->id()]);
        break;
      }
    }

    return $build;
  }

}
