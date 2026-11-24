<?php

namespace Drupal\dept_topics\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\dept_topics\TopicManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 'Topic tags' formatter for site topics display.
 *
 * @FieldFormatter(
 *   id = "dept_topics_topic_tags",
 *   label = @Translation("Topic tags"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
final class TopicTagsFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly TopicManager $topicManager,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      // Use the service ID you actually registered for TopicManager.
      // If yours is dept_topics.topic_manager, change accordingly.
      $container->get('topic.manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $parent_topics = [];
    $sub_topics = [];

    $entities = $items->referencedEntities();

    foreach ($entities as $entity) {
      if ($entity->bundle() === 'topic') {
        $parent_topics[] = $entity;
      }
      elseif ($entity->bundle() === 'subtopic') {
        $sub_topics[] = $entity;

        $parents = $this->topicManager->getParentNodes($entity);

        foreach ($parents as $parent) {
          if (($parent->type ?? NULL) === 'topic') {
            $parent_node = $this->nodeStorage->load($parent->nid);
            if ($parent_node) {
              $parent_topics[] = $parent_node;
            }
          }
        }
      }
    }

    // Reverse to display top level topics first.
    if (!empty($parent_topics)) {
      $parent_topics = array_reverse($parent_topics);
    }

    $nodes = array_merge(array_reverse($sub_topics), $parent_topics);

    foreach ($nodes as $node) {
      $element[$node->id()] = [
        '#type' => 'link',
        '#title' => $node->label(),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $node->id()]),
        '#attributes' => [
          'aria-label' => $node->label() . ' topic',
        ],
        '#cache' => [
          'tags' => ['node:' . $node->id()],
        ],
      ];
    }

    // Reverse ordering to reflect hierarchy as Parent Topic > Topic.
    return array_reverse($element, TRUE);
  }

}
