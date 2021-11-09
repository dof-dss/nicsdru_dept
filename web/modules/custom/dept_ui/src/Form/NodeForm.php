<?php

namespace Drupal\dept_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeForm as CoreNodeForm;

/**
 * Form handler for the node add/edit forms.
 */
class NodeForm extends CoreNodeForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
  }


}
