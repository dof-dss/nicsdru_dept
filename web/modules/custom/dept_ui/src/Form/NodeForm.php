<?php

namespace Drupal\dept_ui\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\node\NodeForm as CoreNodeForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the node add/edit forms.
 */
class NodeForm extends CoreNodeForm {

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
    parent::__construct(
      $entity_repository,
      $temp_store_factory,
      $entity_type_bundle_info,
      $time,
      $current_user,
      $date_formatter
    );

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
    $form = parent::form($form, $form_state);

    $content_groups = [];
    $plugin_id = $this->entity->groupBundle();
    $user_memberships = $this->groupMembership->loadByUser();

    foreach ($user_memberships as $membership) {
      $group = $membership->getGroup();
      $group_options[$group->id()] = $group->label();
    }

    if (!$this->entity->isNew()) {
      $content_groups = array_keys($this->entity->getGroups());
    }

    $form['group_publish'] = [
      '#title' => $this->t('Publish to'),
      '#type' => 'details',
      '#open' => TRUE,
      '#weight' => 500,
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    if (empty($group_options)) {
      $form['group_publish']['groups'] = [
        '#markup' => $this->t("You are not a member of any groups to restrict where content is published."),
      ];
    }
    else {
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save($form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    $groups = $form_state->getValue('groups');
    $group_storage = $this->entityTypeManager->getStorage('group');

    foreach ($groups as $group) {
      $group = $group_storage->load($group);
      $plugin_id = $this->entity->groupBundle();
      // Check if the content plugin is enabled for the current group.
      if (!empty($group) && $group->getGroupType()->hasContentPlugin($plugin_id)) {
        // If this content doesn't exist in the group, add it.
        if (!$group->getContentByEntityId($plugin_id, $this->entity->id())) {
          $group->addContent($this->entity, $plugin_id);
        }
      }
    }
  }

}
