<?php

namespace Drupal\dept_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the department entity edit forms.
 */
class DepartmentForm extends ContentEntityForm {


  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    $departments = \Drupal::database()->query("SELECT id, label FROM {department}")->fetchAllKeyed();
    $domain_options = [];

    foreach ($domains as $domain) {
      if ($domain->id() !== 'dept_admin') {
        $domain_options[$domain->id()] = $domain->label();
      }
    }

    $id_options = array_diff_assoc($domain_options, $departments);

    if (count($id_options) < 1) {
      \Drupal::messenger()->addWarning('All Domains have a Department assigned.');
    }

    $form['id'] = [
      '#type' => 'select',
      '#title' => 'Department',
      '#options' => $id_options,
      '#required' => TRUE,
      '#weight' => -50,
    ];

    $form['label']['widget'][0]['#required'] = FALSE;
    $form['label']['#access'] = FALSE;
    $form['label']['widget'][0]['value']['#required'] = FALSE;

    $form['author'] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#title' => 'Authoring Information'
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
    $domain = \Drupal::entityTypeManager()->getStorage('domain')->load($form_state->getValue('id'));
    $form_state->setValue('label', ['0' => ['value' => $domain->label()]]);

    parent::validateForm($form, $form_state);
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
