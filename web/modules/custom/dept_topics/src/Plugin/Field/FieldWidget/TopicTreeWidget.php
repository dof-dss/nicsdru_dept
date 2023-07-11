<?php

namespace Drupal\dept_topics\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a widget for selecting topics and subtopics.
 *
 * @FieldWidget(
 *   id = "dept_topic_tree_widget",
 *   label = @Translation("Topic Tree"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
final class TopicTreeWidget extends OptionsSelectWidget implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    if (!empty($form['field_domain_access']['widget']['#default_value'])) {
      $current_dept = current($form['field_domain_access']['widget']['#default_value']);
    }

    $topic_manager = \Drupal::service('topic.manager');
    $topics = $topic_manager->getTopicsForDepartment($current_dept);
    $options = [];
    $field = $this->fieldDefinition->getName();
    $default_values = $this->getSelectedOptions($items);

    foreach ($topics as $nid => $topic) {
      $options[$nid] = $topic->label();
    }

    $element = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $default_values,
      '#multiple' => TRUE,
    ];

    $element['#field_suffix'] = [
    '#title' => t('Topic tree'),
    '#type' => 'link',
    '#url' => Url::fromRoute('dept_topics.topic_tree.form',[
      'department'=> $current_dept,
      'field'=> $field,
      'selected' => is_array($default_values) ? implode('+', $default_values) : ''
    ]),
    '#attributes' => [
      'class' => 'use-ajax',
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode([
        'width' => 800,
        'minHeight' => 800,
      ]),
    ],
  ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsGroups() {
    return FALSE;
  }

}
