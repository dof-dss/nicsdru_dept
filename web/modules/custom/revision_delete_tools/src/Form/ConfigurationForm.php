<?php

declare(strict_types=1);

namespace Drupal\revision_delete_tools\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Revision Delete Tools settings for this site.
 */
final class ConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'revision_delete_tools_configuration';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['revision_delete_tools.configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['bulk_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable bulk select and deletion'),
      '#description' => $this->t('Allow multiple selection and deletion of revisions on the node revisions page.'),
      '#default_value' => $this->config('revision_delete_tools.configuration')->get('bulk_delete'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('revision_delete_tools.configuration')
      ->set('bulk_delete', $form_state->getValue('bulk_delete'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
