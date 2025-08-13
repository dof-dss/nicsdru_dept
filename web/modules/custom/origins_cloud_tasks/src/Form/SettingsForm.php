<?php

declare(strict_types=1);

namespace Drupal\origins_cloud_tasks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Origins cloud tasks settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'origins_cloud_tasks_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['origins_cloud_tasks.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $offset_value = $this->config('origins_cloud_tasks.settings')->get('callback_offset') ?? '5';

    $form['callback_offset'] = [
      '#type' => 'range',
      '#title' => $this->t('Callback offset'),
      '#description' => $this->t('@offset_value seconds.', ['@offset_value' => $offset_value]),
      '#min' => 5,
      '#max' => 180,
      '#step' => 1,
      '#default_value' => $offset_value,
      '#attributes' => [
        'oninput' => 'document.getElementById("edit-callback-offset--description").innerText = this.value + " seconds."'
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('origins_cloud_tasks.settings')
      ->set('callback_offset', $form_state->getValue('callback_offset'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
