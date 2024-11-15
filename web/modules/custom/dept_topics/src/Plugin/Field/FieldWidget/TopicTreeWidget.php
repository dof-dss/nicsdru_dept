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
use Drupal\dept_topics\TopicManager;
use Drupal\domain\DomainNegotiatorInterface;
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
   * The Topic manager service.
   *
   * @var \Drupal\dept_topics\TopicManager
   */
  protected TopicManager $topicManager;

  /**
   * The Domain negotiator service.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected DomainNegotiatorInterface $domainNegotiator;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    // Set default values for dynamic properties used by parent classes.
    // See https://www.drupal.org/project/drupal/issues/3046863.
    $this->required = FALSE;
    $this->multiple = FALSE;
    $this->has_value = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings']
    );

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->topicManager = $container->get('topic.manager');
    $instance->domainNegotiator = $container->get('domain.negotiator');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'excluded' => TRUE,
      'limit' => 3,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['excluded'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude from topic child content.'),
      '#description' => $this->t('Prevents this bundle (%bundle) from automatically being added or removed as child content to the selected topics.', ['%bundle' => $form['#bundle']]),
      '#default_value' => $this->getSetting('excluded'),
    ];

    $element['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Selection limit'),
      '#description' => $this->t('The upper limit for the number of topics that can be selected.'),
      '#min' => 1,
      '#max' => 10,
      '#default_value' => $this->getSetting('limit'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Excluded: @excluded', ['@excluded' => ($this->getSetting('excluded')) ? 'Yes' : 'No']);
    $summary[] = $this->t('Selection limit: @limit', ['@limit' => $this->getSetting('limit')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field = $this->fieldDefinition->getName();
    $field_id = Html::getUniqueId($field);
    $default_values = $this->getSelectedOptions($items);
    // @phpstan-ignore-next-line
    $entity = $form_state->getFormObject()->getEntity();
    $settings = $this->getSettings();
    $current_dept = '';
    $options = [];

    // Get current department from the domain_access field.
    if (!empty($form['field_domain_access']['widget']['#default_value'])) {
      $domain_access_values = $form['field_domain_access']['widget']['#default_value'];
      // If we have multiple domain access values check for the existence of 'nigov',
      // remove it and then use the current array value.
      if (count($domain_access_values) > 1) {
        if (($key = array_search('nigov', $domain_access_values)) !== FALSE) {
          unset($domain_access_values[$key]);
        }
      }
      $current_dept = current($domain_access_values);
    }

    // If we cannot determine the department via domain_access, use the current domain department.
    if (empty($current_dept)) {
      $domain = $this->domainNegotiator->getActiveDomain();
      $current_dept = $domain->id();
    }

    // Only list topics/subtopics assigned to the department.
    $topics = $this->topicManager->getTopicsForDepartment($current_dept);

    foreach ($topics as $nid => $topic) {
      $options[$nid] = $topic->label();
    }

    // Limit subtopics to 1 topic selection to avoid the issue with navigating parent hierarchies.
    $selection_limit = ($entity->bundle() === 'subtopic') ? 1 : $this->getSetting('limit');

    $element = [
      '#type' => 'checkboxes',
      '#title' => $this->fieldDefinition->getLabel(),
      '#description' => $this->fieldDefinition->getDescription(),
      '#options' => $options,
      '#multiple' => $selection_limit > 1,
      '#default_value' => $default_values,
      '#required' => $this->fieldDefinition->isRequired(),
      '#attached' => [
        'library' => [
          'dept_topics/topic_select',
        ],
      ],
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'id' => $field_id,
            'class' => 'topic-select',
          ],
        ],
      ],
    ];

    // Affix the topic tree link to the field.
    $element['#field_prefix'] = [
      '#title' => $this->t('Select @label', ['@label' => $this->fieldDefinition->getLabel()]),
      '#type' => 'link',
      '#url' => Url::fromRoute('dept_topics.topic_tree.form', [
        'department' => $current_dept,
        'field' => $field_id,
        'limit' => $selection_limit,
        'selected' => is_array($default_values) ? implode('+', $default_values) : '',
      ]),
      '#disabled' => TRUE,
      '#attributes' => [
        'title' => $this->t('Please wait for the page to fully load'),
        'class' => [
          'button',
          'use-ajax',
          'topic-tree-button',
          'link-button-disable'
        ],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'title' => $this->t('Select @label', ['@label' => $this->fieldDefinition->getLabel()]),
          'width' => 800,
          'minHeight' => 500,
          'position' => ['my' => 'center top', 'at' => 'center top'],
          'draggable' => TRUE,
          'autoResize' => FALSE,
          'dialogClass' => 'topic-widget-modal'
        ]),
      ],
    ];

    $element['#attached']['library'][] = 'dept_topics/topic_tree_widget';
    $element['#cache'] = [
      'contexts' => ['url.site'],
      'tags' => ['dept_topics:' . $current_dept],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Remove any values that are not from a selected checkbox.
    $new_values = array_filter($values, function ($value, $key) {
      return $value !== 0;
    }, ARRAY_FILTER_USE_BOTH);

    return $new_values;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsGroups() {
    return FALSE;
  }

}
