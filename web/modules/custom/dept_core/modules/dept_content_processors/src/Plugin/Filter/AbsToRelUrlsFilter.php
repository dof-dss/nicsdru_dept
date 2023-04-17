<?php

namespace Drupal\dept_content_processors\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_core\DepartmentManager;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to convert absolute URLs in links to relative links
 * providing the active domain matches the department hostname of the link.
 *
 * @Filter(
 *   id = "dept_abs2rel_url",
 *   title = @Translation("Department absolute to relative URL"),
 *   description = @Translation("Converts any absolute URLs for the current department to relative ones."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 * )
 *
 * @internal
 */
class AbsToRelUrlsFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The Department manager.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $departmentManager;

  /**
   * Constructs a MediaEmbed object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The department manager object.
   *
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
      $container->get('department.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (str_contains($text, 'http') === FALSE) {
      return $result;
    }

    // Check we are on a Departmental site we recognise.
    if (!empty($dept = $this->departmentManager->getCurrentDepartment())) {
      $dom = Html::load($text);
      $link_elements = $dom->getElementsByTagName('a');

      foreach ($link_elements as $link) {
        $href = $link->getAttribute('href');

        if (str_starts_with($href, 'http')) {
          // Look at the hostname, if it matches our current domain
          // then remove the hostname from the attribute and update the
          // dom element.
          $url_portions = parse_url($href);
          $host = $url_portions['host'];

          if ($this->hostnameMatchesKnownDepartment($host, $dept->id())) {
            $new_href = $url_portions['path'];
            if (!empty($url_portions['query'])) {
              $new_href .= '?' . $url_portions['query'];
            }

            $link->setAttribute('href', $new_href);
          }
        }
        else {
          // Skip over non-absolute links.
          continue;
        }
      }

      $result = new FilterProcessResult($dom->saveHTML());
    }

    return $result;

  }

  /**
   * Function to perform hostname matching.
   *
   * @param string $host
   *   The hostname found in an absolute link.
   * @param string $dept_id
   *   The machine id of a department entity.
   *
   * @return bool
   *   Whether there's a match between the two.
   */
  protected function hostnameMatchesKnownDepartment(string $host, string $dept_id): bool {
    $match = FALSE;

    // Simplistic matching process - if the dept id is in the hostname
    // then assume it's a match and exit the loop.
    if (preg_match('/' . $dept_id . '/', $host)) {
      $match = TRUE;
    }

    return $match;
  }

}
