<?php

namespace Drupal\dept_example_group_entity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the departmental example group content entity entity edit forms.
 */
class DeptExampleGroupEntityForm extends ContentEntityForm {

  /**
   * The Group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembership;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership
   *   The Group membership loader service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, GroupMembershipLoaderInterface $group_membership) {
    parent::__construct(
      $entity_repository,
      $entity_type_bundle_info,
      $this->time = $time,
    );

    $this->groupMembership = $group_membership;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
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
      '#weight' => 100,
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
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

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

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New departmental example group content entity %label has been created.', $message_arguments));
      $this->logger('dept_example_group_entity')->notice('Created new departmental example group content entity %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The departmental example group content entity %label has been updated.', $message_arguments));
      $this->logger('dept_example_group_entity')->notice('Updated new departmental example group content entity %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.dept_example_group_entity.canonical', ['dept_example_group_entity' => $entity->id()]);
  }

}
