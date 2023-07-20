<?php

namespace Drupal\dept_topics\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
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
  public static function defaultSettings() {
    $settings = [];

    $bundle_info = \Drupal::service('entity_type.bundle.info');
    $bundles = $bundle_info->getBundleInfo('node');

    foreach ($bundles as $bundle_id => $bundle_info) {
      $settings[$bundle_id] = FALSE;
    }

    return $settings;
  }


  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $bundle_info = \Drupal::service('entity_type.bundle.info');
    $bundles = $bundle_info->getBundleInfo('node');

    if (!empty($bundles)) {
      foreach ($bundles as $bundle_id => $bundle_info) {
        $element[$bundle_id] = [
          '#type' => 'checkbox',
          '#title' => $bundle_info['label'],
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field = $this->fieldDefinition->getName();
    $field_id = Html::getUniqueId($field);
    $default_values = $this->getSelectedOptions($items);
    $current_dept = '';
    $options = [];
    $topic_manager = \Drupal::service('topic.manager');

    // Get current department from the domain_access field.
    if (!empty($form['field_domain_access']['widget']['#default_value'])) {
      $current_dept = current($form['field_domain_access']['widget']['#default_value']);
    }

    // If we cannot determine the department via domain_access, use the current domain department.
    if (empty($current_dept)) {
      $domain = \Drupal::service('domain.negotiator')->getActiveDomain();
      $current_dept = $domain->id();
    }

    // Only list topics/subtopics assigned to the department.
    $topics = $topic_manager->getTopicsForDepartment($current_dept);

    foreach ($topics as $nid => $topic) {
      $options[$nid] = $topic->label();
    }

    $element = [
      '#type' => 'select',
      '#title' => t('Topic'),
      '#description' => t('Select a topic for this content. You can choose more than one topic, but choose sparingly and choose the most relevant and specific topic available.'),
      '#options' => $options,
      '#default_value' => $default_values,
      '#multiple' => TRUE,
      '#chosen' => FALSE,
      '#attributes' => [
        'id' => $field_id,
        'class' => ['topic-select'],
      ],
      '#attached' => [
        'library' => [
          'dept_topics/topic_select',
        ],
      ],
    ];

    // Affix the topic tree link to the field.
    $element['#field_prefix'] = [
      '#title' => t('Select topic'),
      '#type' => 'link',
      '#url' => Url::fromRoute('dept_topics.topic_tree.form', [
        'department' => $current_dept,
        'field' => $field_id,
        'selected' => is_array($default_values) ? implode('+', $default_values) : ''
      ]),
      '#attributes' => [
        'class' => ['button', 'use-ajax'],
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
