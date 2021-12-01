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

    $options = [];
    $group_fields = Drupal::service('entity_type.manager')->getStorage('field_storage_config')->loadByProperties([
          'entity_type' => 'group'
      ]
    );

    foreach ($group_fields as $field) {
      $options[$field->id()] = $field->getLabel();
    }

    $form['display_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field to display'),
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

    ksm(\Drupal::service('domain.negotiator')->getActiveDomain(), \Drupal::service('group.group_route_context')->getGroupFromRoute());
//    ksm(\Drupal::service('plugin.manager.domain_group_settings')->getAll()->get('domain_group_site_settings'));

    $build['content'] = [
      '#markup' =>  'foo',
    ];
    return $build;
  }

}

!YdufCeNr2xh4gGntyRxa
