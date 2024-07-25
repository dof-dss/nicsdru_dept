<?php

namespace Drupal\dept_migrate_audit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AuditController extends ControllerBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * @inheritDoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Show audit report.
   *
   * @param string $type
   *   Content type required. Defaults to 'article'.
   * @param int $page
   *   Pager page number.
   *
   * @return array
   *   Render array.
   */
  public function showResults(string $type, int $page = 0) {
    if (empty($type)) {
      return [
        '#markup' => '<div>' . $this->t('No results found. Specify a type in the URL path, eg: article') . '</div>',
      ];
    }

    $header = [
      ['data' => $this->t('D10 Node ID')],
      ['data' => $this->t('D7 Node ID')],
      ['data' => $this->t('Depts')],
      ['data' => $this->t('Type')],
      ['data' => $this->t('Title')],
      ['data' => $this->t('Published')],
      ['data' => $this->t('Created')],
    ];

    $map_table = 'migrate_map_node_' . $type;

    $subquery = $this->database->select('dept_migrate_audit', 'dma');
    $subquery->fields('dma', ['uuid']);

    $query = $this->database->select('node_field_data', 'nfd');
    $query->join($map_table, 'map', 'nfd.nid = map.destid1');
    $query->join('node__field_domain_access', 'nfda', 'nfda.entity_id = nfd.nid');
    $query->fields('nfd', ['nid', 'type', 'title', 'status', 'created']);
    $query->fields('map', ['sourceid1', 'sourceid2']);
    $query->fields('nfda', ['field_domain_access_target_id']);
    $query->condition('map.sourceid1', $subquery, 'NOT IN');
    $query->orderBy('nfd.created', 'DESC');

    $pager = $query
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(50);

    $results = $pager->execute()->fetchAll();

    $rows = [];
    foreach ($results as $row) {
      $dept_id = $row->field_domain_access_target_id;
      if ($dept_id === 'nigov') {
        $dept_id = 'northernireland';
      }
      else {
        $dept_id .= '-ni';
      }

      $rows[] = [
        'nid' => $row->nid,
        'd7nid' => Link::fromTextAndUrl($row->sourceid2, Url::fromUri('https://' . $dept_id . '.gov.uk/node/' . $row->sourceid2, ['absolute' => TRUE]))->toString(),
        'depts' => $row->field_domain_access_target_id,
        'type' => $row->type,
        'title' => $row->title,
        'status' => $row->status,
        'created' => \Drupal::service('date.formatter')->format($row->created),
      ];
    }

    $build = [
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('Nothing to display.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];

    return $build;
  }

}
