<?php

namespace Drupal\dept_topics\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\dept_topics\TopicManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
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
   * The Topic manager service.
   */
  protected TopicManager $topicManager;

  /**
   * The Domain negotiator service.
   */
  protected DomainNegotiatorInterface $domainNegotiator;

  /**
   * Current route match.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The bundle displaying this field widget.
   */
  protected string $bundle;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    TopicManager $topic_manager,
    DomainNegotiatorInterface $domain_negotiator,
    RouteMatchInterface $route_match,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    // Set default values for dynamic properties used by parent classes.
    // See https://www.drupal.org/project/drupal/issues/3046863.
    $this->required = FALSE;
    $this->multiple = FALSE;
    $this->has_value = FALSE;

    $this->bundle = $field_definition->getTargetBundle();
    $this->topicManager = $topic_manager;
    $this->domainNegotiator = $domain_negotiator;
    $this->routeMatch = $route_match;
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
      $configuration['third_party_settings'],
      // NOTE: use your actual service id for TopicManager:
      // in your earlier example you used 'topic.manager' but many modules use
      // 'dept_topics.topic_manager'. Keep whatever you have defined.
      $container->get('topic.manager'),
      $container->get('domain.negotiator'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['excluded' => TRUE] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['excluded'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude from topic child content.'),
      '#description' => $this->t(
        'Prevents this bundle (%bundle) from automatically being added or removed as child content to the selected topics.',
        ['%bundle' => $form['#bundle']]
      ),
      '#default_value' => $this->getSetting('excluded'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->t('Excluded: @excluded', ['@excluded' => $this->getSetting('excluded') ? 'Yes' : 'No']);
    $summary[] = $this->t('Selection limit: @limit', ['@limit' => TopicManager::maximumTopicsForType($this->bundle)]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field = $this->fieldDefinition->getName();
    $field_id = Html::getUniqueId($field);
    $default_values = $this->getSelectedOptions($items);

    $current_dept = '';
    $current_nid = '';
    $options = [];

    $node = $this->routeMatch->getParameter('node');

    if (empty($node)) {
      // @phpstan-ignore-next-line
      $node = $form_state->getFormObject()->getEntity();
    }

    // If updating a node fetch the ID to disable this entry in the topic tree.
    if ($node instanceof NodeInterface) {
      // Set to 0 to prevent InvalidParameterException, typically when returning
      // from a node preview.
      $current_nid = $node->id() ?? 0;
    }

    // Get current department from the domain_access field.
    if (!empty($form['field_domain_access']['widget']['#default_value'])) {
      $domain_access_values = $form['field_domain_access']['widget']['#default_value'];

      // If we have multiple domain access values check for 'nigov', remove it,
      // then use the current array value.
      if (count($domain_access_values) > 1) {
        $key = array_search('nigov', $domain_access_values, TRUE);
        if ($key !== FALSE) {
          unset($domain_access_values[$key]);
        }
      }

      $current_dept = (string) current($domain_access_values);
    }

    // If we cannot determine the department via domain_access, use active domain.
    if ($current_dept === '') {
      $domain = $this->domainNegotiator->getActiveDomain();
      $current_dept = $domain->id();
    }

    // Only list topics/subtopics assigned to the department.
    $topics = $this->topicManager->getTopicsForDepartment($current_dept);
    foreach ($topics as $nid => $topic) {
      $options[$nid] = $topic->label();
    }

    $selection_limit = TopicManager::maximumTopicsForType($this->bundle);

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

    $modal_url = Url::fromRoute('dept_topics.topic_tree.form', [
      'department' => $current_dept,
      'field' => $field_id,
      'limit' => $selection_limit,
      'selected' => is_array($default_values) ? implode('+', $default_values) : '',
      'nid' => $current_nid,
    ])->toString();

    $element['#field_prefix'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Select @label', ['@label' => $this->fieldDefinition->getLabel()]),
      '#attributes' => [
        'id' => 'site-topics-tree-open-button',
        'data-topic-modal-url' => $modal_url,
        'data-topic-modal-title' => $this->t('Select @label', ['@label' => $this->fieldDefinition->getLabel()]),
        'class' => ['button', 'topic-tree-button', 'link-button-disable'],
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
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    // Remove any values that are not from a selected checkbox.
    return array_filter($values, static fn($value) => $value !== 0);
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsGroups(): bool {
    return FALSE;
  }

}
