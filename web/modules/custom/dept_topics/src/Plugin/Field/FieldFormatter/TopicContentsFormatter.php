<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Plugin\Field\FieldFormatter;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Topic Contents' formatter.
 *
 * @FieldFormatter(
 *   id = "dept_topics_topic_contents",
 *   label = @Translation("Topic contents"),
 *   field_types = {"entity_reference"},
 * )
 */
final class TopicContentsFormatter extends EntityReferenceEntityFormatter implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  /**
   * Constructs an EntityReferenceEntityFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   The moderation information service.
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    protected ModerationInformationInterface $moderationInformation,
    protected Connection $database,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
      $logger_factory,
      $entity_type_manager,
      $entity_display_repository
    );
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('content_moderation.moderation_information'),
      $container->get('database'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'renderChildModerationStatus',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display_type' => 'node_view',
      'view_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['display_type'] = [
      '#title' => t('Display type'),
      '#type' => 'select',
      '#description' => $this->t('Select how each element should be displayed.'),
      '#options' => [
        'node_view' => t('Node view (Display)'),
        'link_label' => t('Link label'),
      ],
      '#default_value' => $this->getSetting('display_type'),
    ];

    $elements['view_mode'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#title' => $this->t('View mode'),
      '#default_value' => $this->getSetting('view_mode'),
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="settings[formatter][settings][display_type]"]' => [
            'value' => 'node_view',
          ],
        ],
      ]
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    if ($this->getSetting('display_type') === 'node_view') {
      $summary[] = t('Node view: @view_mode', ['@view_mode' => $this->getSetting('view_mode')]);
    }
    else {
      $summary[] = t('Link label');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    $display_type = $this->getSetting('display_type');
    $view_mode = 'default';

    if ($display_type === 'node_view') {
      $view_mode = $this->getSetting('view_mode') ?? 'default';
    }

    $elements = [];
    $topic = $items->getEntity();

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {

      // START 1/2: Departmental custom code.
      // NOTE: An archived child has it's ID removed from all topic 'child contents'
      // entity reference fields, both live and revision, so we don't need to handle
      // that moderation state in this formatter.

      $is_unpublished = !$this->moderationInformation->isDefaultRevisionPublished($entity);

      // If child is unpublished and anonymous user, don't render this child.
      if ($is_unpublished && $this->currentUser->isAnonymous()) {
        continue;
      }

      // @phpstan-ignore-next-line
      $child_current_topics = array_column($entity->get('field_site_topics')->getValue(), 'target_id');

      // If anonymous user and published but doesn't have a site topic for this
      // topic then don't render this child.
      // Prevents display of this child if it had a draft with this topic ID.
      if ($this->currentUser->isAnonymous() && !in_array($topic->id(), $child_current_topics)) {
        continue;
      }

      $moderation_states = [];

      // If child is unpublished and has a site topic for this topic, fetch the moderation state.
      // Typically when new child content is added and is in a 'draft' or 'needs review' phase.
      if ($is_unpublished && in_array($topic->id(), $child_current_topics)) {
        $moderation_states = $this->lookupChildRevisions($entity, $topic, NULL);
      }

      // Fetch the moderation state for child that has a site topic entry for this topic
      // but not in the published revision. We pass the child's published revision ID so we
      // only fetch the moderation states after the published revision.
      if ($this->currentUser->isAuthenticated() && !in_array($topic->id(), $child_current_topics)) {
        $revision_id = $this->moderationInformation->getDefaultRevisionId('node', $entity->id());

        $moderation_states = $this->lookupChildRevisions($entity, $topic, $revision_id);

        if (empty($moderation_states)) {
          continue;
        }
      }

      if ($display_type === 'node_view') {

        // END 1/2: Departmental custom code.
        // START CORE EntityReferenceEntityFormatter code.

        // Due to render caching and delayed calls, the viewElements() method
        // will be called later in the rendering process through a '#pre_render'
        // callback, so we need to generate a counter that takes into account
        // all the relevant information about this field and the referenced
        // entity that is being rendered.
        $recursive_render_id = $items->getFieldDefinition()->getTargetEntityTypeId()
          . $items->getFieldDefinition()->getTargetBundle()
          . $items->getName()
          // We include the referencing entity, so we can render default images
          // without hitting recursive protections.
          . $items->getEntity()->id()
          . $entity->getEntityTypeId()
          . $entity->id();

        if (isset(static::$recursiveRenderDepth[$recursive_render_id])) {
          static::$recursiveRenderDepth[$recursive_render_id]++;
        }
        else {
          static::$recursiveRenderDepth[$recursive_render_id] = 1;
        }

        // Protect ourselves from recursive rendering.
        if (static::$recursiveRenderDepth[$recursive_render_id] > static::RECURSIVE_RENDER_LIMIT) {
          $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity %entity_type: %entity_id, using the %field_name field on the %parent_entity_type:%parent_bundle %parent_entity_id entity. Aborting rendering.', [
            '%entity_type' => $entity->getEntityTypeId(),
            '%entity_id' => $entity->id(),
            '%field_name' => $items->getName(),
            '%parent_entity_type' => $items->getFieldDefinition()->getTargetEntityTypeId(),
            '%parent_bundle' => $items->getFieldDefinition()->getTargetBundle(),
            '%parent_entity_id' => $items->getEntity()->id(),
          ]);
          return $elements;
        }

        $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
        $elements[$delta] = $view_builder->view($entity, $view_mode, $entity->language()->getId());

        // START 2/2: Departmental custom code.
        if (!empty($moderation_states)) {
          $elements[$delta]['#moderation_states'] = $moderation_states;
          $elements[$delta]['#pre_render'][] = [static::class, 'renderChildModerationStatus'];
        }
        // END 2/2: Departmental custom code.

        // Add a resource attribute to set the mapping property's value to the
        // entity's URL. Since we don't know what the markup of the entity will
        // be, we shouldn't rely on it for structured data.
        if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
          $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
        }

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }

        $elements[$delta]['#entity'] = $entity;
        $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
        // END CORE EntityReferenceEntityFormatter code.
      }
      else {
        $label = $entity->label();
        $uri = $entity->toUrl();

        if (!$entity->isNew()) {
          $elements[$delta] = [
            '#type' => 'link',
            '#title' => $label,
            '#url' => $uri,
            '#options' => $uri->getOptions(),
          ];

          if (!empty($moderation_states)) {
            $elements[$delta]['#moderation_states'] = $moderation_states;
            // We have to add the Core link pre_render callback or the markup
            // will not be generated.
            $elements[$delta]['#pre_render'] = [
              [static::class, 'renderChildModerationStatus'],
              ['Drupal\Core\Render\Element\Link', 'preRenderLink'],
            ];
          }
        }
      }
    }

    return $elements;
  }

  /**
   *  Return revisions states for a child referencing a given topic.
   *
   * @param \Drupal\Core\Entity\EntityInterface $child
   *   The node to lookup revision states.
   * @param \Drupal\Core\Entity\EntityInterface $topic
   *   The topic a child node references.
   * @param int|null $revision_id
   *   Revision ID for comparison.
   *
   * @return array
   *   List of revision states for the given child and topic.
   */
  protected function lookupChildRevisions(EntityInterface $child, EntityInterface $topic, int|null $revision_id = NULL): array {

    $query = $this->database->select('content_moderation_state_field_revision', 'modstate');
    $query->join('node_revision__field_site_topics', 'revtopics', 'revtopics.entity_id = modstate.content_entity_id AND revtopics.revision_id = modstate.content_entity_revision_id');
    $query->fields('modstate', ['moderation_state']);
    $query->condition('revtopics.entity_id', $child->id());
    $query->condition('revtopics.field_site_topics_target_id', $topic->id());
    $query->condition('modstate.moderation_state', ['draft', 'needs_review'], 'IN');
    $query->orderBy('revtopics.revision_id', 'DESC');

    // If revision ID is provided, only return revisions created after that ID.
    if (!empty($revision_id)) {
      $query->condition('revtopics.revision_id', $revision_id, '>');
    }

    return $query->execute()->fetchCol();
  }

  /**
   * Render callback to add child node moderation status for the rendered topic.
   *
   * @param array $element
   *   The child render array element.
   *
   * @return array
   *   Child node render array.
   */
  public static function renderChildModerationStatus(array $element) {
    if (\Drupal::currentUser()->isAuthenticated() && array_key_exists('#moderation_states', $element)) {
      foreach ($element['#moderation_states'] as $moderation_state) {
        $element['#attributes']['class'][] = 'ms__' . $moderation_state;
      }
    }

    return $element;
  }

}
