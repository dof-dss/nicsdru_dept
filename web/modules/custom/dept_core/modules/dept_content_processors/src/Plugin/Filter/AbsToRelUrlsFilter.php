<?php

namespace Drupal\dept_content_processors\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_core\DepartmentManager;
use Drupal\dept_core\Entity\Department;
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
 */
class AbsToRelUrlsFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The Department manager.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $departmentManager;

  /**
   * The Department ID.
   *
   * @var string
   */
  protected string $departmentId;

  /**
   * The department hostname/url.
   *
   * @var string
   */
  protected string $departmentHostname;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->departmentManager = $container->get('department.manager');

    // Check we are on a Departmental site we recognise.
    $department = $instance->departmentManager->getCurrentDepartment();
    if ($department instanceof Department) {
      $instance->departmentId = $department->id();
      $instance->departmentHostname = $department->url();
    }

    return $instance;
  }

  /**
   * Department ID getter.
   *
   * @return string
   *   The machine ID of the Department.
   */
  public function getDepartmentId(): string {
    return $this->departmentId;
  }

  /**
   * Department ID setter.
   *
   * @param string $departmentId
   *   The machine ID of the Department.
   */
  public function setDepartmentId(string $departmentId): void {
    $this->departmentId = trim($departmentId);
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (str_contains($text, 'http') === FALSE) {
      return $result;
    }

    if (!empty($this->getDepartmentId())) {
      $dom = Html::load($text);
      $link_elements = $dom->getElementsByTagName('a');

      foreach ($link_elements as $link) {
        $href = $link->getAttribute('href');

        if (str_starts_with($href, 'http')) {
          // Look at the hostname, if it matches our current domain
          // then remove the hostname from the attribute and update the
          // dom element.
          $url_portions = parse_url($href);

          if ($this->shouldRewriteUrl($url_portions) && $this->hostnameMatchesKnownDepartment($href)) {

            $new_href = '';

            if (!empty($url_portions['path'])) {
              $new_href .= $url_portions['path'];
            }
            if (!empty($url_portions['query'])) {
              $new_href .= '?' . $url_portions['query'];
            }
            if (!empty($url_portions['fragment'])) {
              $new_href .= '#' . $url_portions['fragment'];
            }

            $link->setAttribute('href', $new_href);
          }
        }
      }

      $output = $dom->saveHTML();

      // Remove the HTML markup created when the text is loaded into HTML DOM.
      $output = preg_replace('/(<!DOCTYPE.+>)?\n*(<html>)?(<body>)?(.+)(<\/body><\/html>)/m', "$4", $output);

      $result = new FilterProcessResult($output);
    }

    return $result;

  }

  /**
   * Function to assess whether to process a URL or not
   * based on the presence or absence of certain url components.
   *
   * @param array $url_portions
   *   Array or URL components, as specified in parse_url().
   *   https://www.php.net/manual/en/function.parse-url.php.
   */
  protected function shouldRewriteUrl(array $url_portions): bool {
    $shouldRewrite = FALSE;

    foreach (['path', 'query', 'fragment'] as $item) {
      if (!empty($url_portions[$item])) {
        $shouldRewrite = TRUE;
        break;
      }
    }

    return $shouldRewrite;
  }

  /**
   * Function to perform hostname matching.
   *
   * @param string $url
   *   The URL to match.
   *
   * @return bool
   *   Whether there's a match between the two.
   */
  protected function hostnameMatchesKnownDepartment(string $url): bool {
    // Match the first part of the domain to the department.
    return (bool) preg_match('#' . $this->departmentHostname . '#', $url);
  }

}
