<?php

namespace Drupal\dept_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a departmental details block.
 *
 * @Block(
 *   id = "dept_core_departmental_details",
 *   admin_label = @Translation("Departmental details"),
 *   category = @Translation("Custom")
 * )
 */
class DepartmentalDetailsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'foo' => $this->t('Hello world!'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['foo'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Foo'),
      '#default_value' => $this->configuration['foo'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['foo'] = $form_state->getValue('foo');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#markup' => $this->t('It works!'),
    ];
    return $build;
  }

}
