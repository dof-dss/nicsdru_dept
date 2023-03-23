<?php

namespace Drupal\dept_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_core\DepartmentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a departmental details block.
 *
 * @Block(
 *   id = "dept_core_departmental_details",
 *   admin_label = @Translation("Departmental details"),
 *   category = @Translation("Departmental sites"),
 *   context_definitions = {
 *    "current_department" = @ContextDefinition("entity:department")
 *  }
 * )
 */
class DepartmentalDetailsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'context_mapping' => [
        'current_department' => '@department.current_department_context:department',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    // Methods of the Department class to call.
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
      '#default_value' => $this->configuration['display_field'] ?? '',
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
    $build = [];
    $dept = $this->getContextValue('current_department');

    if (is_object($dept) && method_exists($dept, 'id')) {
      $build = [
        '#cache' => [
          'tags' => ['department:' . $dept->id()],
        ],
      ];

      // Call the display field method on the departmental class.
      $field_data = call_user_func([
        $dept,
        $this->getConfiguration()['display_field'],
      ]);

      if (array_key_exists("#field_name", $field_data)) {
        // @phpstan-ignore-next-line  Doesn't like magic methods.
        $build['content'] = $dept->get($field_data['#field_name'])->view('default');
      }
    }

    return $build;
  }

}
