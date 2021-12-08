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

    $form['toolbar_sites']['toolbar_sites_lando_protocol'] = [
      '#type' => 'radios',
      '#title' => $this->t('Lando protocol'),
      '#description' => $this->t('Rewrites the protocol for the lando hostname'),
      '#options' => ['HTTPS', 'HTTP'],
      '#default_value' => $this->config('dept_dev.settings')->get('toolbar_sites_lando_protocol') ?? '0',
      '#states' => [
        'invisible' => [
          ':input[name="toolbar_sites_lando_hostname"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('dept_dev.settings');
    $config->set('toolbar_sites_lando_hostname', $form_state->getValue('toolbar_sites_lando_hostname'));
    $config->set('toolbar_sites_lando_protocol', $form_state->getValue('toolbar_sites_lando_protocol'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
