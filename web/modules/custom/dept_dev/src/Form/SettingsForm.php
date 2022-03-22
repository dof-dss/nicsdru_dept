<?php

namespace Drupal\dept_dev\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Departmental devtools settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'departmental_devtools_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['departmental_devtools.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['node_source_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display link to source node'),
      '#description' => $this->t('Provides a link to the original Drupal 7 page for the current node'),
      '#default_value' => $this->config('departmental_devtools.settings')->get('node_source_link'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('departmental_devtools.settings');
    $config->set('node_source_link', $form_state->getValue('node_source_link'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
