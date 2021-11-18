<?php

namespace Drupal\dept_user\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure departmental users settings for this site.
 */
class EditUserGroupMembershipForm extends FormBase {

  /**
   * The Group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembership;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user account to edit group membership.
   *
   * @var Drupal\Core\Session\AccountInterface
   */
  protected $userAccount;

  /**
   * All Group entities.
   *
   * @var array
   */
  protected $allGroups;

  /**
   * The users group memberships.
   *
   * @var array
   */
  protected $userMemberships;

  /**
   * Class constructor.
   *
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership
   *   The Group membership loader service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(GroupMembershipLoaderInterface $group_membership, EntityTypeManagerInterface $entity_type_manager) {
    $this->groupMembership = $group_membership;
    $this->entityTypeManager = $entity_type_manager;
    $this->userAccount = \Drupal::routeMatch()->getParameter('user');
    $this->allGroups = $this->entityTypeManager->getStorage('group')->loadMultiple();
    $this->userMemberships = $this->groupMembership->loadByUser($this->userAccount);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('group.membership_loader'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_user_group_membership_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL) {

    // Create an array of Group options, we can't use $this->allGroups directly
    // as it consists of the Group id index and Group entity as the value.
    foreach ($this->allGroups as $group) {
      $groups_options[$group->id()] = $group->label();
    }

    foreach ($this->userMemberships as $membership) {
      $users_groups[] = $membership->getGroup()->id();
    }

    $form['group_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Group membership for @name (%email)', [
        '@name' => $this->userAccount->getAccountName(),
        '%email' => $this->userAccount->getEmail(),
      ]),
    ];

    $form['group_wrapper']['groups'] = [
      '#type' => 'checkboxes',
      '#options' => $groups_options,
      '#default_value' => $users_groups,
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $groups = array_filter($form_state->getValue('groups'));
    $all_groups = $this->entityTypeManager->getStorage('group')->loadMultiple();

    // Create an array indexed by group ID so we can compare.
    foreach ($this->userMemberships as $membership) {
      $memberships[$membership->getGroup()->id()] = $membership;
    }

    // Add user to groups.
    foreach (array_diff_key($groups, $memberships) as $id) {
      $all_groups[$id]->addMember($this->userAccount);
    }

    // Remove user from groups.
    foreach (array_diff_key($memberships, $groups) as $id => $group) {
      $all_groups[$id]->removeMember($this->userAccount);
    }
  }

}
