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
    return 'dept_dev_settings';
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

    $form['node_source_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display link to source node'),
      '#description' => $this->t('Provides a link to the original Drupal 7 page for the current node'),
      '#default_value' => $this->config('dept_dev.settings')->get('node_source_link'),
    ];

    $form['node_source_domains'] = [
      '#type' => 'details',
      '#title' => $this->t('Domain ID to URL mappings'),
      '#description' => 'A list of Drupal 7 domain ID\'s and the corresponding website url.',
      '#open' => TRUE,
    ];

    $domains = $this->config('dept_dev.settings')->get('node_source_domains');

    foreach ($domains as $key => $domain) {
      $form['node_source_domains']['node_source_domain_' . $key] = [
        '#type' => 'textfield',
        '#title' => $this->t('ID: @id', ['@id' => $key]),
        '#default_value' => $domain,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_vals = $form_state->getValues();
    $domains = [];

    // Fetch all the domain mapping id's and values.
    foreach ($form_vals as $key => $val) {
      if (substr($key, 0, 19) === "node_source_domain_") {
        $domains[substr($key, 19)] = $val;
      }
    }

    $config = $this->config('dept_dev.settings');
    $config->set('node_source_domains', $domains);
    $config->set('node_source_link', $form_vals['node_source_link']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
