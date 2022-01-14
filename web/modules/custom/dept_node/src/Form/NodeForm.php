<?php

namespace Drupal\dept_node\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\group\GroupMembershipLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the node add/edit forms.
 *
 * Handles the 'Publish to' display and relationship of nodes to Group entities.
 *
 * #internal Drupal\node\NodeForm
 *
 */
class NodeForm extends ContentEntityForm {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The Current User object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The Group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembership;

  /**
   * Constructs a NodeForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership
   *   The Group membership loader service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    PrivateTempStoreFactory $temp_store_factory,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL,
    AccountInterface $current_user,
    DateFormatterInterface $date_formatter,
    GroupMembershipLoaderInterface $group_membership
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
    $this->groupMembership = $group_membership;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('tempstore.private'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('group.membership_loader')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Try to restore from temp store, this must be done before calling
    // parent::form().
    $store = $this->tempStoreFactory->get('node_preview');

    // Attempt to load from preview when the uuid is present unless we are
    // rebuilding the form.
    $request_uuid = \Drupal::request()->query->get('uuid');
    if (!$form_state->isRebuilding() && $request_uuid && $preview = $store->get($request_uuid)) {
      /** @var \Drupal\Core\Form\FormStateInterface $preview */

      $form_state->setStorage($preview->getStorage());
      $form_state->setUserInput($preview->getUserInput());

      // Rebuild the form.
      $form_state->setRebuild();

      // The combination of having user input and rebuilding the form means
      // that it will attempt to cache the form state which will fail if it is
      // a GET request.
      $form_state->setRequestMethod('POST');

      $this->entity = $preview->getFormObject()->getEntity();
      $this->entity->in_preview = NULL;

      $form_state->set('has_been_previewed', TRUE);
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entity;

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit @type</em> @title', [
        '@type' => node_get_type_label($node),
        '@title' => $node->label(),
      ]);
    }

    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $node->getChangedTime(),
    ];

    $form = parent::form($form, $form_state);

    $form['advanced']['#attributes']['class'][] = 'entity-meta';

    $form['meta'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#weight' => -10,
      '#title' => $this->t('Status'),
      '#attributes' => ['class' => ['entity-meta__header']],
      '#tree' => TRUE,
      '#access' => $this->currentUser->hasPermission('administer nodes'),
    ];
    $form['meta']['published'] = [
      '#type' => 'item',
      '#markup' => $node->isPublished() ? $this->t('Published') : $this->t('Not published'),
      '#access' => !$node->isNew(),
      '#wrapper_attributes' => ['class' => ['entity-meta__title']],
    ];
    $form['meta']['changed'] = [
      '#type' => 'item',
      '#title' => $this->t('Last saved'),
      '#markup' => !$node->isNew() ? $this->dateFormatter->format($node->getChangedTime(), 'short') : $this->t('Not saved yet'),
      '#wrapper_attributes' => ['class' => ['entity-meta__last-saved']],
    ];
    $form['meta']['author'] = [
      '#type' => 'item',
      '#title' => $this->t('Author'),
      '#markup' => $node->getOwner()->getAccountName(),
      '#wrapper_attributes' => ['class' => ['entity-meta__author']],
    ];

    $form['status']['#group'] = 'footer';

    // Node author information for administrators.
    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Authoring information'),
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['node-form-author'],
      ],
      '#attached' => [
        'library' => ['node/drupal.node'],
      ],
      '#weight' => 90,
      '#optional' => TRUE,
    ];

    if (isset($form['uid'])) {
      $form['uid']['#group'] = 'author';
    }

    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }

    // Node options for administrators.
    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Promotion options'),
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['node-form-options'],
      ],
      '#attached' => [
        'library' => ['node/drupal.node'],
      ],
      '#weight' => 95,
      '#optional' => TRUE,
    ];

    if (isset($form['promote'])) {
      $form['promote']['#group'] = 'options';
    }

    if (isset($form['sticky'])) {
      $form['sticky']['#group'] = 'options';
    }

    $form['#attached']['library'][] = 'node/form';

    // Return if the bundle isn't present as a Group content plugin.
    if (!method_exists($this->entity, 'groupBundle')) {
      return $form;
    }

    $content_groups = [];
    $plugin_id = $this->entity->groupBundle();

    if ($plugin_id === NULL) {
      return $form;
    }
    $user_memberships = $this->groupMembership->loadByUser();

    foreach ($user_memberships as $membership) {
      $group = $membership->getGroup();
      $group_options[$group->id()] = $group->label();
    }

    if (!$this->entity->isNew()) {
      $content_groups = array_keys($this->entity->getGroups());
    }

    // If the user is a member of more than one Group/Department then we
    // display a 'Publish to' option, otherwise publish to the sole Group they
    // are a member of or warn if no Group memberships are present.
    if (count($group_options) > 1) {
      $form['group_publish'] = [
        '#title' => $this->t('Publish to'),
        '#type' => 'details',
        '#open' => TRUE,
        '#weight' => 500,
        '#attributes' => [
          'class' => ['container-inline'],
        ],
      ];

      // TODO: Using the last group to determine the disabled state to prevent
      // the user from selecting groups when we can't publish this content type
      // to those. Do we need to look at the enabled entity types for each group
      // and toggle the disabled state for each individual checkbox?
      $form['group_publish']['groups'] = [
        '#type' => 'checkboxes',
        '#options' => $group_options,
        '#disabled' => !$group->getGroupType()->hasContentPlugin($plugin_id),
        '#default_value' => $content_groups,
      ];

      if ($form['group_publish']['groups']['#disabled']) {
        $form['group_publish']['info'] = [
          '#markup' => '<p>' . $this->t('This content is not enabled for groups') . '</p>',
        ];
      }
    }
    elseif (count($group_options) === 1) {
      $form['groups'] = [
        '#type' => 'hidden',
        '#value' => array_key_first($group_options),
      ];
    }
    else {
      $form['warning'] = [
        '#markup' => '<b>' . $this->t("WARNING: You are not a member of any Departments and this content will not be visible on any sites.") . '</b>',
        '#weight' => -500,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    $node = $this->entity;
    $preview_mode = $node->type->entity->getPreviewMode();

    $element['submit']['#access'] = $preview_mode != DRUPAL_REQUIRED || $form_state->get('has_been_previewed');

    $element['preview'] = [
      '#type' => 'submit',
      '#access' => $preview_mode != DRUPAL_DISABLED && ($node->access('create') || $node->access('update')),
      '#value' => $this->t('Preview'),
      '#weight' => 20,
      '#submit' => ['::submitForm', '::preview'],
    ];

    if (array_key_exists('delete', $element)) {
      $element['delete']['#weight'] = 100;
    }

    return $element;
  }

  /**
   * Form submission handler for the 'preview' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function preview(array $form, FormStateInterface $form_state) {
    $store = $this->tempStoreFactory->get('node_preview');
    $this->entity->in_preview = TRUE;
    $store->set($this->entity->uuid(), $form_state);

    $route_parameters = [
      'node_preview' => $this->entity->uuid(),
      'view_mode_id' => 'full',
    ];

    $options = [];
    $query = $this->getRequest()->query;
    if ($query->has('destination')) {
      $options['query']['destination'] = $query->get('destination');
      $query->remove('destination');
    }
    $form_state->setRedirect('entity.node.preview', $route_parameters, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $node = $this->entity;
    $insert = $node->isNew();
    $node->save();
    $node_link = $node->toLink($this->t('View'))->toString();
    $context = [
      '@type' => $node->getType(),
      '%title' => $node->label(),
      'link' => $node_link
    ];
    $t_args = [
      '@type' => node_get_type_label($node),
      '%title' => $node->toLink()->toString()
    ];

    $group_form_values = $form_state->getValue('groups');

    if (is_array($group_form_values)) {
      // Use array filter to remove an unchecked groups from the values.
      $groups = array_filter($group_form_values);
    }
    else {
      $groups[$group_form_values] = $group_form_values;
    }

    $group_storage = $this->entityTypeManager->getStorage('group');
    $plugin_id = $this->entity->groupBundle();

    if ($insert) {
      foreach ($groups as $group) {
        $group = $group_storage->load($group);
        // Check if the content plugin is enabled for the current group.
        if (!empty($group) && $group->getGroupType()->hasContentPlugin($plugin_id)) {
          $group->addContent($this->entity, $plugin_id);
        }
      }

      $this->logger('content')->notice('@type: added %title.', $context);
      $this->messenger()->addStatus($this->t('@type %title has been created.', $t_args));
    }
    else {
      $entity_groups = $this->entity->getGroups();

      // Add entity to groups.
      foreach (array_diff_key($groups, $entity_groups) as $id => $label) {
        $group = $group_storage->load($id);
        // Check if the content plugin is enabled for the current group.
        if (!empty($group) && $group->getGroupType()->hasContentPlugin($plugin_id)) {
          $group->addContent($this->entity, $plugin_id);
        }
      }

      // Remove entity from groups.
      foreach (array_diff_key($entity_groups, $groups) as $id => $label) {
        $group = $group_storage->load($id);
        $group_entity_relations = $group->getContentByEntityId($plugin_id, $this->entity->id());
        foreach ($group_entity_relations as $relation) {
          $relation->delete();
        }
      }

      $this->logger('content')->notice('@type: updated %title.', $context);
      $this->messenger()->addStatus($this->t('@type %title has been updated.', $t_args));
    }

    if ($node->id()) {
      $form_state->setValue('nid', $node->id());
      $form_state->set('nid', $node->id());
      if ($node->access('view')) {
        $form_state->setRedirect(
          'entity.node.canonical',
          ['node' => $node->id()]
        );
      }
      else {
        $form_state->setRedirect('<front>');
      }

      // Remove the preview entry from the temp store, if any.
      $store = $this->tempStoreFactory->get('node_preview');
      $store->delete($node->uuid());
    }
    else {
      // In the unlikely case something went wrong on save, the node will be
      // rebuilt and node form redisplayed the same way as in preview.
      $this->messenger()->addError($this->t('The post could not be saved.'));
      $form_state->setRebuild();
    }
  }

}
