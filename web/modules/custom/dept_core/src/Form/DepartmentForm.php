<?php

namespace Drupal\dept_core\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the department entity edit forms.
 */
final class DepartmentForm extends ContentEntityForm {

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    private readonly Connection $database,
  ) {
    // ContentEntityForm expects entity_type.manager on the parent.
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    /** @var static $instance */
    $instance = parent::create($container);
    // Parent::create() sets a bunch of required stuff; then we set our deps.
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $domains = $this->entityTypeManager->getStorage('domain')->loadMultiple();

    // Load existing department IDs => labels.
    $departments = $this->database
      ->select('department', 'd')
      ->fields('d', ['id', 'label'])
      ->execute()
      ->fetchAllKeyed();

    $domain_options = [];
    foreach ($domains as $domain) {
      if ($domain->id() !== 'dept_admin') {
        $domain_options[$domain->id()] = $domain->label();
      }
    }

    // Only allow domain IDs not already used by a department.
    $id_options = array_diff_key($domain_options, $departments);

    if ($this->entity->isNew() && count($id_options) < 1) {
      $this->messenger()->addWarning($this->t('All Domains have a Department assigned.'));
    }

    if ($this->entity->isNew()) {
      $form['id'] = [
        '#type' => 'select',
        '#title' => $this->t('Department'),
        '#options' => $id_options,
        '#required' => TRUE,
        '#access' => TRUE,
        '#weight' => -50,
      ];
    }
    else {
      $form['id'] = [
        '#type' => 'hidden',
        '#value' => $this->entity->id(),
      ];
    }

    // Hide label field (it is set from the chosen domain).
    $form['label']['widget'][0]['#required'] = FALSE;
    $form['label']['#access'] = FALSE;
    $form['label']['widget'][0]['value']['#required'] = FALSE;

    $form['author'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => $this->t('Authoring information'),
    ];

    $form['status']['#group'] = 'footer';
    $form['uid']['#group'] = 'author';
    $form['created']['#group'] = 'author';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // On edit, 'id' is hidden, but still present in form state.
    $domain_id = $form_state->getValue('id') ?: $this->entity->id();

    $domain = $this->entityTypeManager->getStorage('domain')->load($domain_id);
    if ($domain) {
      $form_state->setValue('label', ['0' => ['value' => $domain->label()]]);
    }

    parent::validateForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();

    $message_arguments = ['%label' => $entity->toLink()->toString()];
    $logger_arguments = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New department %label has been created.', $message_arguments));
        $this->logger('dept_core')->notice('Created new department %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The department %label has been updated.', $message_arguments));
        $this->logger('dept_core')->notice('Updated department %label.', $logger_arguments);
        break;
    }

    $form_state->setRedirect('entity.department.canonical', ['department' => $entity->id()]);
    return $result;
  }

}
