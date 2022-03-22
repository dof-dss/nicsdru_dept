<?php

namespace Drupal\dept_dev\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Department sites: development tools settings for this site.
 */
class ToolbarSitesSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'departmental_devtools_settings_toolbar_sites';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['departmental_devtools.settings.toolbar_sites'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['toolbar_sites'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toolbar: Sites'),
    ];

    $form['toolbar_sites']['lando_hostname'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable lando hostname'),
      '#description' => $this->t('Rewrites the domain links to use the lndo.site hostname'),
      '#default_value' => $this->config('departmental_devtools.settings.toolbar_sites')->get('lando_hostname'),
    ];

    $form['toolbar_sites']['lando_protocol'] = [
      '#type' => 'radios',
      '#title' => $this->t('Lando protocol'),
      '#description' => $this->t('Rewrites the protocol for the lando hostname'),
      '#options' => ['HTTPS', 'HTTP'],
      '#default_value' => $this->config('departmental_devtools.settings.toolbar_sites')->get('lando_protocol') ?? '0',
      '#states' => [
        'invisible' => [
          ':input[name="lando_hostname"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('departmental_devtools.settings.toolbar_sites');
    $config->set('lando_hostname', $form_state->getValue('lando_hostname'));
    $config->set('lando_protocol', $form_state->getValue('lando_protocol'));
    $config->save();
    Cache::invalidateTags(['departmental_devtools_tools_sites']);
    parent::submitForm($form, $form_state);
  }

}
