<?php

namespace Drupal\dept_migrate_nodes\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateProcessPlugin(
 *   id = "body_field_process"
 * )
 */

class BodyFieldProcess extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $migrateLookupManager;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $migrate_lookup_manager
   *   The D7 to D10 migrate lookup manager service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, MigrateUuidLookupManager $migrate_lookup_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migrateLookupManager = $migrate_lookup_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('dept_migrate.migrate_uuid_lookup_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    /*
     * $value is an array such as:
     * $value = [
     *   'value' => '<p>Some content</p>,
     *   'summary' => 'Details of something here',
     *   'format' => 'filtered_html,
     * ];
     */
    $value['value'] = $this->tidyupEmbeddedMediaInParagraphs($value['value']);
    $value['value'] = $this->handleMalformedLinks($value['value']);
    $value['value'] = $this->handleUnwantedSpaces($value['value']);
    $value['value'] = $this->updateD7ToD10CanonicalPaths($value['value']);

    return $value;
  }

  /**
   * Internal function to process broken or malformed links
   * in an HTML string.
   *
   * @param string $content
   *   The content string to process.
   * @return string
   *   The processed content string.
   */
  private function handleUnwantedSpaces(string $content) {
    // Progressive removal of unwanted spaces/line breaks.
    $content = preg_replace('/(<p>[<br>|\s]*&nbsp;<\/p>)/im', '', $content);
    $content = str_replace('&nbsp;</p>', '', $content);
    $content = str_replace('&nbsp;', ' ', $content);

    return $content;
  }

  /**
   * Internal function to process unwanted spaces
   * and line breaks in an HTML string.
   *
   * @param string $content
   *   The content string to process.
   * @return string
   *   The processed content string.
   */
  private function handleMalformedLinks(string $content) {
    // Strip out empty <a> tags: see DEPT-618.
    // Example: /articles/removal-industrial-derating-draft-equality-impact-screening.
    $content = preg_replace('/<a>(.+)<\/a>/iU', '$1', $content);

    return $content;
  }

  /**
   * Function to re-arrange some incorrectly positioned D7 media embed tokens.
   * This regex turns <p>[[fid:123...]]Text that follows</p>
   * into [[fid:123...]]<p>Text that follows</p>.
   *
   * NGL this really hurt my brain with a lot of trial and error.
   *
   * @param string $content
   *   The content string to process.
   *
   * @return string
   *   The processed content string.
   */
  private function tidyupEmbeddedMediaInParagraphs(string $content) {
    $pattern = '/<(\w+)>(\[\[.*?\]\])(.*?)<\/\1>/s';
    $replacement = '$2<$1>$3</$1>';

    $content = preg_replace($pattern, $replacement, $content);

    return $content;
  }

  /**
   * Function to lookup and replace D7 canonical paths with their
   * D10 equivalents. These /node/123 paths are preferred over aliased
   * paths as they aren't expected to change. Text filters are relied on
   * to convert those canonical paths into aliased paths where they are
   * available, to whatever their present value is.
   *
   * @param string $content
   *   HTML string to process.
   *
   * @return string
   *   The processed content string.
   */
  private function updateD7ToD10CanonicalPaths(string $content) {
    $pattern = '|\/node\/(\d+)|';
    $matches = [];
    preg_match_all($pattern, $content, $matches);

    /*
     * Structure returned for a match looks like this.
     * 0 => array:1 [
     *    0 => "/node/2110"
     * ]
     * 1 => array:1 [
     *    0 => "2110"
     * ]
     */
    if (!empty($matches[1])) {
      foreach ($matches[1] as $d7_canonical_nid) {
        $d10_lookup = $this->migrateLookupManager->lookupBySourceNodeId([$d7_canonical_nid]);
        $d10_lookup = reset($d10_lookup);

        if (!empty($d10_lookup) && !empty($d10_lookup['nid'])) {
          $d10_canonical_path = '/node/' . $d10_lookup['nid'];
          $content = str_replace('/node/' . $d7_canonical_nid, $d10_canonical_path, $content);
        }
      }
    }

    return $content;
  }

}
