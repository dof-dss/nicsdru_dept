<?php

namespace Drupal\dept_core\Plugin\Block;

use Drupal;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a departmental details block.
 *
 * @Block(
 *   id = "dept_core_departmental_details",
 *   admin_label = @Translation("Departmental details"),
 *   category = @Translation("Departmental sites")
 * )
 */
class DepartmentalDetailsBlock extends BlockBase {

  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $options = [
      'accessToInformation' => 'Access to information',
      'contactInformation' => 'Contact Information',
      'managementAndStructure' => 'Management and structure',
      'socialMediaLinks' => 'Social media links',
      'location' => 'Location',
    ];

    $form['display_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Information to display'),
      '#options' => $options,
      '#default_value' => $this->configuration['display_field'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['display_field'] = $form_state->getValue('display_field');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build['content'] = [
      '#markup' =>  'content',
    ];
    return $build;
  }

}
