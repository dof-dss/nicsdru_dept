<?php

namespace Drupal\dept_user\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
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
   * @var Drupal\Core\Session\AccountInterface $account
   */
  protected $userAccount;

  /**
   * Class constructor.
   *
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership
   *   The Group membership loader service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $user_account
   *   The user account to update group membership against.
   */
  public function __construct(GroupMembershipLoaderInterface $group_membership, EntityTypeManagerInterface $entity_type_manager, AccountInterface $user_account) {
    $this->groupMembership = $group_membership;
    $this->entityTypeManager = $entity_type_manager;
    $this->userAccount = $user_account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('group.membership_loader'),
      $container->get('entity_type.manager'),
      $account = \Drupal::routeMatch()->getParameter('user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dept_user_add_user_to_group';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL) {

    $group_entities = $this->entityTypeManager->getStorage('group')->loadMultiple();
    $user_memberships = $this->groupMembership->loadByUser($this->userAccount);

    foreach ($group_entities as $group) {
      $all_groups[$group->id()] = $group->label();
    }

    foreach ($user_memberships as $membership) {
      $users_groups[] = $membership->getGroup()->id();
    }

    $form['groups'] = [
      '#type' => 'checkboxes',
      '#options' => $all_groups,
      '#default_value' => $users_groups,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if ($form_state->getValue('example') != 'example') {
      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
