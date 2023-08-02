<?php

namespace Drupal\dept_topics\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\dept_topics\TopicManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeForm;
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
    $settings['excluded'] = TRUE;
    return $settings;
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

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Excluded: @excluded', ['@excluded' => ($this->getSetting('excluded')) ? 'Yes' : 'No']);

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
      $current_dept = current($form['field_domain_access']['widget']['#default_value']);
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

    $element = [
      '#type' => 'checkboxes',
      '#title' => t('Topic'),
      '#description' => t('Select a topic for this content. You can choose more than one topic, but choose sparingly and choose the most relevant and specific topic available.'),
      '#options' => $options,
      '#default_value' => $default_values,
      '#required' => TRUE,
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

    if ($form_state->getFormObject() instanceof NodeForm && !$this->topicManager->isExcludedFromChildTopics($entity)) {
      $element['#suffix'] = $this->t('This content will be automatically added to/removed from the topics as child content.');
    }

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
