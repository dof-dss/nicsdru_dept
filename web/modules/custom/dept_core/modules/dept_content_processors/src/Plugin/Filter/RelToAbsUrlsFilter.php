<?php

namespace Drupal\dept_content_processors\Plugin\Filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_core\DepartmentManager;
use Drupal\dept_core\Rel2AbsUrl;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Filter(
 *   id = "rel_to_abs_url",
 *   title = @Translation("Relative to Absolute URL"),
 *   description = @Translation("Transform relative URLs to absolute URLs"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 *   settings = {
 *     "process_domains" = {},
 *   },
 *   weight = 100,
 * )
 */
class RelToAbsUrlsFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Department manager.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $departmentManager;

  /**
   * Rel2AbsUrl service object.
   *
   * @var \Drupal\dept_core\Rel2AbsUrl
   */
  protected $rel2AbsUrl;

  /**
   * Filter constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The department manager.
   * @param \Drupal\dept_core\Rel2AbsUrl $rel2abs_url
   *   The rel2abs url service object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, DepartmentManager $department_manager, Rel2AbsUrl $rel2abs_url) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->departmentManager = $department_manager;
    $this->rel2AbsUrl = $rel2abs_url;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('department.manager'),
      $container->get('rel2abs_url')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    // Check we are on a Departmental site.
    if (!empty($dept = $this->departmentManager->getCurrentDepartment())) {
      // Fetch all the domains checked for processing from the filter settings.
      $process_domains = $this->settings['process_domains'];
      $domains_ids = array_keys(array_filter($process_domains));

      // Check if the current domain is selected for processing urls.
      if (in_array(substr($dept->id(), 6), $domains_ids)) {
        // Convert links added by the LinkIt module.
        $updated_text = preg_replace_callback(
         '/data-entity-uuid="(.+)" href="(\/\S+)"/m',
          function ($matches) {
            $node = $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $matches[1]]);
            $node = reset($node);
            $url = $this->rel2AbsUrl->handleUrl($matches[2], $node);
            return 'href="' . $url . '"';
          },
          $result
        );

        if ($updated_text) {
          $result = new FilterProcessResult($updated_text);
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $domains = [];
    $depts = $this->departmentManager->getAllDepartments();

    foreach ($depts as $dept) {
      $domains[$dept->id()] = $dept->name();
    }

    $form['process_domains'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Domains to rewrite links'),
      '#options' => $domains,
      '#default_value' => $this->settings['process_domains']
    ];

    return $form;
  }

}
