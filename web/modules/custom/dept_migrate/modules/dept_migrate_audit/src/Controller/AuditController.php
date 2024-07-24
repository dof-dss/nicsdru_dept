<?php

namespace Drupal\dept_migrate_audit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
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
   * @param int $page
   *   Pager page number.
   *
   * @return array
   *   Render array.
   */
  public function showResults(int $page = 0) {
    $header = [
      ['data' => $this->t('D10 Node ID'), 'field' => 'nid'],
      ['data' => $this->t('D7 Node ID'), 'field' => 'd7nid'],
      ['data' => $this->t('Depts'), 'field' => 'depts'],
      ['data' => $this->t('Type'), 'field' => 'type'],
      ['data' => $this->t('Title'), 'field' => 'title'],
      ['data' => $this->t('Published'), 'field' => 'status'],
      ['data' => $this->t('Created'), 'field' => 'created'],
    ];

    // TODO: vary by type somehow.
    $map_table = 'migrate_map_node_article';

    $subquery = $this->database->select('dept_migrate_audit', 'dma');
    $subquery->fields('dma', ['uuid']);

    $query = $this->database->select('node_field_data', 'nfd');
    $query->join($map_table, 'map', 'nfd.nid = map.destid1');
    $query->join('node__field_domain_access', 'nfda', 'nfda.entity_id = nfd.nid');
    $query->fields('nfd', ['nid', 'type', 'title', 'status', 'created']);
    $query->fields('map', ['sourceid1', 'sourceid2']);
    $query->fields('nfda', ['field_domain_access_target_id']);
    $query->condition('map.sourceid1', $subquery, 'NOT IN');

    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(50)
      ->element(0);

    $results = $pager->execute()->fetchAll();

    $rows = [];
    foreach ($results as $row) {
      $rows[] = [
        'nid' => $row->nid,
        'd7nid' => $row->sourceid2,
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
