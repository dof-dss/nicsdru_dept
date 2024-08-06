<?php

declare(strict_types=1);

namespace Drupal\dept_migrate_audit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dept_core\DepartmentManager;
use Drupal\dept_migrate_audit\MigrationAuditBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Department sites: migration audit form.
 */
final class MigrationAuditForm extends FormBase {

  /**
   * Constructs Migrate Audit Form.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\dept_migrate_audit\MigrationAuditBatchService $auditProcessService
   *   The Migration Audit Process service.
   * @param \Drupal\dept_core\DepartmentManager $deptManager
   *   The Department Manager.
   * @param string $type
   *   A content type (node bundle).
   */
  public function __construct(
    protected Connection $database,
    protected MigrationAuditBatchService $auditProcessService,
    protected DepartmentManager $deptManager,
    protected string $type) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dept_migrate_audit.audit_batch_service'),
      $container->get('department.manager'),
      $container->get('current_route_match')->getParameter('type'),
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dept_migrate_audit_migration_audit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    ksm($this->type);

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
      if ($type_id === $this->type) {
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
          'dept_migrate_audit.migration_audit',
          ['type' => $type_id],
          [
            'attributes' => [
              'class' => ['link'],
              'style' => 'padding: 0 5px',
            ],
          ])->toRenderable();

        $top_links[] = $link_element;
      }
    }

    if (empty($this->type)) {
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

    $map_table = 'migrate_map_node_' . $this->type;

    $subquery = $this->database->select('dept_migrate_audit', 'dma');
    $subquery->fields('dma', ['uuid']);
    $subquery->condition('dma.type', $type_map[$this->type], 'IN');

    $current_dept = $this->deptManager->getCurrentDepartment();
    $dept_filter = $current_dept->id();

    $query = $this->database->select('node_field_data', 'nfd');
    $query->join($map_table, 'map', 'nfd.nid = map.destid1');
    $query->join('node__field_domain_access', 'nfda', 'nfda.entity_id = nfd.nid');
    $query->fields('nfd', ['nid', 'type', 'title', 'status', 'created']);
    $query->fields('map', ['sourceid1', 'sourceid2']);
    $query->fields('nfda', ['field_domain_access_target_id']);
    $query->condition('map.sourceid1', $subquery, 'NOT IN');
    $query->condition('nfda.field_domain_access_target_id', $dept_filter);
    $query->orderBy('nfd.created', 'DESC');

    $num_rows = $query->countQuery()->execute()->fetchField();

    // @phpstan-ignore-next-line
    $pager = $query
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(25);

    $results = $pager->execute()->fetchAll();

    // Get total count and last import timestamp.
    $last_import_time = $this->database->query("SELECT last_import FROM {dept_migrate_audit} ORDER BY last_import DESC LIMIT 1")->fetchField();

    if (empty($last_import_time)) {
      return [
        '#markup' => $this->t('Audit database table empty, @link.', ['@link' => Link::createFromRoute('Process audit data', 'dept_migrate_audit.migrate_audit_process_data')->toString()])
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

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Send'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('The message has been sent.'));
    $form_state->setRedirect('<front>');
  }

}
