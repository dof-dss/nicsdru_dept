<?php

namespace Drupal\dept_node\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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

    $entity = $this->getContextValue('node');

    $build['content'] = [
      '#markup' => $entity->label(),
    ];
    return $build;
  }

}
