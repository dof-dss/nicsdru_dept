<?php

namespace Drupal\dept_migrate_audit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
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
    $top_links = [];
    $types = [
      'application' => 'Application',
      'article' => 'Article',
      'collection' => 'Collection',
      'consultation' => 'Consultation',
      'contact' => 'Contact',
      'gallery' => 'Gallery',
      'heritage_site' => 'Heritage site',
      'link' => 'Link',
      'news' => 'News',
      'page' => 'Page',
      'profile' => 'Profile',
      'protected_area' => 'Protected area',
      'publication' => 'Publication (including secure)',
      'subtopic' => 'Subtopic',
      'topic' => 'Topic',
      'ual' => 'Unlawfully at large',
    ];

    foreach ($types as $type_id => $label) {
      if ($type_id === $type) {
        $top_links[] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'style' => 'padding: 0 5px',
          ],
          '#value' => $label,
        ];
      }
      else {
        $link_element = Link::createFromRoute($label,
          'dept_migrate_audit.results',
          ['type' => $type_id],
          [
            'attributes' => [
              'class' => ['link'],
              'style' => 'padding: 0 5px',
            ]
          ])->toRenderable();

        $top_links[] = $link_element;
      }
    }

    if (empty($type)) {
      return $top_links + [
        '#markup' => '<div>' . $this->t('No results found. Specify a type in the URL path, eg: article') . '</div>',
      ];
    }

    $header = [
      ['data' => $this->t('D10 Node ID')],
      ['data' => $this->t('D7 Node ID')],
      ['data' => $this->t('Depts')],
      ['data' => $this->t('Type')],
      ['data' => $this->t('Title')],
      ['data' => $this->t('D10 Publish status')],
      ['data' => $this->t('Created')],
    ];

    // D7 to D10 content type map.
    $type_map = [
      'application' => 'application',
      'article' => ['article', 'page'],
      'collection' => 'collection',
      'consultation' => 'consultation',
      'contact' => 'contact',
      'gallery' => 'gallery',
      'heritage_site' => 'heritage_site',
      'link' => 'link',
      'news' => ['news', 'press_release'],
      'page' => 'page',
      'profile' => 'profile',
      'protected_area' => 'protected_area',
      'publication' => ['publication', 'secure_publication'],
      'subtopic' => 'subtopic',
      'topic' => ['topic', 'landing_page'],
      'ual' => 'ual',
    ];

    $map_table = 'migrate_map_node_' . $type;

    $subquery = $this->database->select('dept_migrate_audit', 'dma');
    $subquery->fields('dma', ['uuid']);
    $subquery->condition('dma.type', $type_map[$type], 'IN');

    $query = $this->database->select('node_field_data', 'nfd');
    $query->join($map_table, 'map', 'nfd.nid = map.destid1');
    $query->join('node__field_domain_access', 'nfda', 'nfda.entity_id = nfd.nid');
    $query->fields('nfd', ['nid', 'type', 'title', 'status', 'created']);
    $query->fields('map', ['sourceid1', 'sourceid2']);
    $query->fields('nfda', ['field_domain_access_target_id']);
    $query->condition('map.sourceid1', $subquery, 'NOT IN');
    $query->orderBy('nfd.created', 'DESC');

    $num_rows = $query->countQuery()->execute()->fetchField();

    // @phpstan-ignore-next-line
    $pager = $query
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(50);

    $results = $pager->execute()->fetchAll();

    // Get total count and last import timestamp.
    $last_import_time = $this->database->query("SELECT last_import FROM {dept_migrate_audit} ORDER BY last_import DESC LIMIT 1")->fetchField();

    if (empty($last_import_time)) {
      return [
        '#markup' => 'Audit database table not found.'
      ];
    }

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
        'nid' => Link::fromTextAndUrl($row->nid, Url::fromRoute('entity.node.canonical', ['node' => $row->nid])),
        'd7nid' => Link::fromTextAndUrl($row->sourceid2, Url::fromUri('https://' . $dept_id . '.gov.uk/node/' . $row->sourceid2, ['absolute' => TRUE]))->toString(),
        'depts' => $row->field_domain_access_target_id,
        'type' => $row->type,
        'title' => $row->title,
        'status' => ($row->status == 1) ? $this->t('Published') : $this->t('Not published'),
        'created' => \Drupal::service('date.formatter')->format($row->created),
      ];
    }

    $build = [];

    $build[] = $top_links;

    $build[] = [
      '#markup' => $this->t('<h3>:numrows results. </h3>', [
        ':numrows' => $num_rows,
      ]),
    ];

    $build[] = [
      '#markup' => $this->t("<p>NB: Content shared across department
          sites will appear more than once in the table.
          <strong>Last audit data imported on :importtime</strong></p>", [
            ':importtime' => \Drupal::service('date.formatter')
              ->format($last_import_time, 'medium'),
          ]),
    ];

    $build[] = [
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
