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
    return 'dept_dev_settings_toolbar_sites';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dept_dev.settings.toolbar_sites'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['toolbar_sites'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('URL settings'),
    ];

    $form['toolbar_sites']['config_hostnames'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable configuration hostnames'),
      '#description' => $this->t('Uses the hostnames in the loaded configuration (i.e. lando or edge domains)'),
      '#default_value' => $this->config('dept_dev.settings.toolbar_sites')->get('config_hostnames'),
    ];

    $form['toolbar_sites']['url_protocol'] = [
      '#type' => 'radios',
      '#title' => $this->t('URL protocol'),
      '#description' => $this->t('Protocol to use for site links'),
      '#options' => ['1' => 'HTTPS', '0' => 'HTTP'],
      '#default_value' => $this->config('dept_dev.settings.toolbar_sites')->get('url_protocol') ?? '0',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('dept_dev.settings.toolbar_sites');
    $config->set('config_hostnames', $form_state->getValue('config_hostnames'));
    $config->set('url_protocol', $form_state->getValue('url_protocol'));
    $config->save();
    Cache::invalidateTags(['dept_dev_tools_sites']);
    parent::submitForm($form, $form_state);
  }

}
