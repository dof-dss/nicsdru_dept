<?php

namespace Drupal\topic_tree\Plugin\Field\FieldWidget;

use Drupal\Core\Database\Connection;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'topic_tree_widget' field widget.
 *
 * @FieldWidget(
 *   id = "topic_tree_widget",
 *   label = @Translation("Topic Tree"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
final class TopicTreeWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, Connection $database) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('database')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {

    \Drupal\topic_tree\Controller\TopicTree::build

//    $query = \Drupal::entityQuery('node')
//      ->condition('type', 'topic')
//      ->condition('field_root_topic', 1)
//      ->accessCheck(TRUE);
//    $results = $query->execute();
//
//    $root_topics = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($results);
//
//    foreach ($root_topics as $topic) {
//      $options[$topic->id()] = $topic->label();
//    }
//
//    $element['value'] = $element + [
//      '#type' => 'select',
//      '#options' => $options,
//      '#default_value' => $items[$delta]->value ?? NULL,
//    ];
//    return $element;

//    $form_element['dialog_link'] = [
//      '#type' => 'link',
//      '#title' => $label,
//      '#url' => Url::fromRoute(
//        'entity_reference_tree.widget_form',
//        [
//          'field_edit_id' => $edit_id,
//          'bundle' => $str_target,
//          'entity_type' => $str_target_type,
//          'theme' => $this->getSetting('theme'),
//          'dots' => $this->getSetting('dots'),
//          'dialog_title' => $dialog_title,
//          'limit' => $this->fieldDefinition->getFieldStorageDefinition()->getCardinality(),
//        ]),
//      '#attributes' => [
//        'class' => [
//          'use-ajax',
//          'button',
//        ],
//      ],
//    ];

  }

}
