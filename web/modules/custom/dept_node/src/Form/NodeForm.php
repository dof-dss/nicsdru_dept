<?php

namespace Drupal\dept_node\Form;

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
 *
 * Handles the 'Publish to' display and relationship of nodes to Group entities.
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
  public function save($form, FormStateInterface $form_state) {
    // Set this variable before the parent save which will assign the node
    // an id and thus isNew() will return false even if this is a new item.
    $is_new = $this->entity->isNew();

    parent::save($form, $form_state);

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

    if ($is_new) {
      foreach ($groups as $group) {
        $group = $group_storage->load($group);
        // Check if the content plugin is enabled for the current group.
        if (!empty($group) && $group->getGroupType()->hasContentPlugin($plugin_id)) {
          $group->addContent($this->entity, $plugin_id);
        }
      }
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
    }
  }

}
