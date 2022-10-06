<?php

namespace Drupal\dept_node\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\dept_node\ContentTopics;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Produces a link of topics and subtopics for a node.
 *
 * @Block(
 *   id = "dept_node_topics_list",
 *   admin_label = @Translation("Node: topics and subtopics list"),
 *   category = @Translation("Departmental sites")
 * )
 */
class TopicsListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\dept_node\ContentTopics
   */
  protected $contentTopicsService;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dept_node\ContentTopics $content_topics_service
   *   The content topics service object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A route match object for finding the current node parameter.
   */
  public function __construct(array $configuration,
                              string $plugin_id,
                              array $plugin_definition,
                              ContentTopics $content_topics_service,
                              RouteMatchInterface $route_match) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->contentTopicsService = $content_topics_service;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dept_node.topics'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      $topics = $this->contentTopicsService->getTopics($node, TRUE, TRUE);
      $build['topics_subtopics_list'] = [
        '#theme' => 'topics_subtopics_list',
        '#title' => t('Topics'),
        '#items' => $topics,
        '#render_links' => TRUE,
        '#include_subtopics' => TRUE,
      ];
    }

    return $build;
  }

}
