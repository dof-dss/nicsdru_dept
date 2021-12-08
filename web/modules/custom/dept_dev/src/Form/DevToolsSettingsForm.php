<?php

namespace Drupal\dept_dev\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Department sites: development tools settings for this site.
 */
class DevToolsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dept_dev_sites_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dept_dev.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['toolbar_sites'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toolbar: Sites'),
    ];

    $form['toolbar_sites']['toolbar_sites_lando_hostname'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable lando hostname'),
      '#description' => $this->t('Rewrites the domain links to use the lndo.site hostname'),
      '#default_value' => $this->config('dept_dev.settings')->get('toolbar_sites_lando_hostname'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dept_dev.settings')
      ->set('toolbar_sites_lando_hostname', $form_state->getValue('toolbar_sites_lando_hostname'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
