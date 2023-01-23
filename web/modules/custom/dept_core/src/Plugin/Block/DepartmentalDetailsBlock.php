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
 *   category = @Translation("Departmental sites")
 * )
 */
class DepartmentalDetailsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Department manager service.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $departmentManager;

  /**
   * Constructs a new MasqueradeBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The department manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DepartmentManager $department_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->departmentManager = $department_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('department.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
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
    $dept = $this->departmentManager->getCurrentDepartment();

    if (empty($dept)) {
      return [];
    }

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

    // Bit of a hack here to determine if field data was returned. This problem
    // arose on edge and throws a WSOD with the error "TypeError: strlen():
    // Argument #1 ($string) must be of type string, array given in strlen()"
    // but unable to reproduce locally.
    if (array_key_exists("#field_name", $field_data)) {
      $build['content'] = $field_data;
    }

    return $build;
  }

}
