<?php

namespace Drupal\dept_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the department entity edit forms.
 */
class DepartmentForm extends ContentEntityForm {


  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    $domain_options = [];

    foreach ($domains as $domain) {
      if ($domain->id() !== 'dept_admin') {
        $domain_options[$domain->id()] = $domain->label();
      }
    }

    $form['id'] = [
      '#type' => 'select',
      '#title' => 'Department',
      '#options' => $domain_options,
      '#required' => TRUE,
      '#weight' => -50,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->setValue('id', 'justice');

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
