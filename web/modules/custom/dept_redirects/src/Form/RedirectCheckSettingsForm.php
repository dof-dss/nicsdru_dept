<?php

namespace Drupal\dept_redirects\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class RedirectCheckSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dept_redirects.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dept_redirects_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dept_redirects.settings');

    $form['batch_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Batch size'),
      '#options' => [50 => 50, 100 => 100, 250 => 250, 500 => 500, 1000 => 1000],
      '#default_value' => $config->get('batch_size') ?? 50,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dept_redirects.settings')
      ->set('batch_size', $form_state->getValue('batch_size'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
