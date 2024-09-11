<?php

declare(strict_types=1);

namespace Drupal\dept_migrate_audit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\dept_core\DepartmentManager;
use Drupal\dept_migrate_audit\MigrationAuditBatchService;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger factory.
   * @param string $type
   *   A content type (node bundle).
   */
  public function __construct(
    protected Connection $database,
    protected MigrationAuditBatchService $auditProcessService,
    protected DepartmentManager $deptManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected LoggerChannelInterface $logger,
    protected string $type,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dept_migrate_audit.audit_batch_service'),
      $container->get('department.manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('logger.factory')->get('dept_migrate_audit'),
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
            'style' => 'display: inline-block; padding: 5px; margin: 0 5px',
            'class' => ['button', 'button--primary'],
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
              'style' => 'display: inline-block; padding: 5px; margin: 0 5px',
              'class' => ['button'],
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

      $form['notice'] = [
        '#markup' => $this->t('Audit database table empty. Click the button below to generate.')
      ];

      $form['actions'] = [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Generate audit data'),
          '#name' => 'submitGenerateAuditData',
        ],
      ];

      return $form;
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

      $rows[$row->nid] = [
        Link::fromTextAndUrl($row->nid, Url::fromRoute('entity.node.canonical', ['node' => $row->nid])),
        Link::fromTextAndUrl($row->sourceid2, Url::fromUri('https://' . $dept_id . '.gov.uk/node/' . $row->sourceid2, ['absolute' => TRUE]))->toString(),
        $row->field_domain_access_target_id,
        $row->type,
        $row->title,
        ($row->status == 1) ? $this->t('Published') : $this->t('Not published'),
        \Drupal::service('date.formatter')->format($row->created),
      ];
    }

    $form[] = $top_links;

    $form[] = [
      '#markup' => $this->t('<h3>:numrows results. </h3>', [
        ':numrows' => $num_rows,
      ]),
    ];

    $form[] = [
      '#markup' => $this->t("<p>NB: Content listed is only for the current department.
        <strong>Last audit data imported on :importtime</strong></p>", [
          ':importtime' => \Drupal::service('date.formatter')->format($last_import_time, 'medium'),
        ]
      ),
    ];

    $form[] = [
      'table' => [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $rows,
        '#empty' => $this->t('Nothing to display.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions'
    ];

    $form['actions']['delete'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Delete selected nodes'),
        '#name' => 'submitDelete',
        '#attributes' => [
          'class' => ['button--danger'],
        ],
      ],
    ];

    $form['actions']['audit_data'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Regenerate audit data'),
        '#name' => 'submitGenerateAuditData',
        '#attributes' => [
          'class' => ['button--secondary'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submit_button = $form_state->getTriggeringElement();

    if ($submit_button['#name'] === 'submitDelete') {
      $nids = array_keys(array_filter($form_state->getValue('table')));

      if (empty($nids)) {
        $form_state->setErrorByName('actions', 'To delete you must select at least one item from the table.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $submit_button = $form_state->getTriggeringElement();

    if ($submit_button['#name'] === 'submitGenerateAuditData') {
      $this->auditProcessService->setupBatch();
      return;
    }

    $nids = array_keys(array_filter($form_state->getValue('table')));

    if (!empty($nids)) {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $media_storage = $this->entityTypeManager->getStorage('media');
      $mediaEntitiesToDelete = [];
      $nodes = $node_storage->loadMultiple($nids);

      // For each node we iterate the Media fields for that bundle, extract the
      // referenced entities and check if that media item is referenced in any
      // other fields for that media type.
      foreach ($nodes as $node) {
        foreach ($this->bundleMediaFields($this->type) as $media_type => $bundle_reference_type) {
          foreach ($bundle_reference_type as $field_name) {
            $referenced_entities = $node->get($field_name)->referencedEntities();

            foreach ($referenced_entities as $referenced_entity) {
              $mid = $referenced_entity->id();
              $record_count = 0;

              foreach ($this->mediaFieldTableData($media_type) as $reference_field => $reference_field_tables) {
                // We're only checking the main field table and not the revision
                // fields as we haven't migrated revisions.
                $count = $this->database->select($reference_field_tables[0])
                  ->condition($reference_field . '_target_id', $mid)
                  ->countQuery()
                  ->execute()
                  ->fetchField();

                $record_count += $count;
              }

              // If we only have 1 entry for the Media entity across all field
              // tables for that Media bundle then it only belongs to this node
              // and is added to the array for deletion.
              if ($record_count === 1) {
                $mediaEntitiesToDelete[] = $mid;
              }
            }
          }
        }
      }

      $mediaEntitiesToDelete = array_unique($mediaEntitiesToDelete);

      // Delete media entities only for these nodes.
      if ($mediaEntitiesToDelete) {
        $media_entities = $media_storage->loadMultiple($mediaEntitiesToDelete);
        $media_storage->delete($media_entities);
        $this->markEntitiesAsIgnored($media_entities);
        $this->logMediaEntities($media_entities);
      }

      // Delete the selected nodes.
      $nodes = $node_storage->loadMultiple($nids);
      $node_storage->delete($nodes);
      $this->markEntitiesAsIgnored($nodes);

      if ($mediaEntitiesToDelete) {
        \Drupal::messenger()->addMessage($this->t('Deleted @total @type nodes and @media_total Media entities', [
          '@type' => $this->type,
          '@total' => count($nids),
          '@media_total' => count($mediaEntitiesToDelete)
        ]));
      }
      else {
        \Drupal::messenger()->addMessage($this->t('Deleted @total @type nodes.', [
          '@type' => $this->type,
          '@total' => count($nids)
        ]));
      }
    }

  }

  /**
   *
   * Returns an array of media fields for the given node bundle.
   *
   * @param string $bundle
   *   The bundle ID.
   *
   * @return array
   *   Array of Media fields for the given bundle.
   */
  protected function bundleMediaFields(string $bundle) {
    $type_fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    $bundle_reference_fields = [];

    foreach ($type_fields as $type_field) {
      if ($type_field->getType() === 'entity_reference') {
        $settings = $type_field->getSettings();
        if ($settings['handler'] === 'default:media') {
          foreach ($settings['handler_settings']['target_bundles'] as $target) {
            $bundle_reference_fields[$target][] = $type_field->getName();
          }
        }
      }
    }

    return $bundle_reference_fields;
  }

  /**
   *
   * Return database tables used by the given Media bundle ID.
   *
   * @param string $type
   *   A Media entity bundle ID.
   *
   * @return array
   *   Array of fields and their database tables for the bundle ID.
   */
  protected function mediaFieldTableData(string $type): array {
    $reference_fields = [];
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    foreach ($node_types as $node_type) {
      $fields = $this->entityFieldManager->getFieldDefinitions('node', $node_type->id());

      foreach ($fields as $field) {
        if ($field->getType() === 'entity_reference') {
          $settings = $field->getSettings();
          if ($settings['handler'] === 'default:media') {
            foreach ($settings['handler_settings']['target_bundles'] as $target) {
              $tables = $this->entityTypeManager->getStorage('node')->getTableMapping()->getAllFieldTableNames($field->getName());
              $reference_fields[$target][$field->getName()] = $tables;
            }
          }
        }
      }
    }

    return $reference_fields[$type];
  }

  /**
   * Logs details about Media entities that have been deleted.
   *
   * @param array $entities
   *   The media entities to log.
   *
   */
  protected function logMediaEntities($entities): void {
    $message = $this->t('Media entries deleted for @type : ', ['@type' => $this->type]);

    foreach ($entities as $entity) {
      $source_uuid = $this->database->select("migrate_map_d7_file_media_" . $entity->bundle(), 'mm')
        ->fields('mm', ['sourceid1'])
        ->condition('destid1', $entity->id())
        ->execute()
        ->fetchField();
      $message .= $entity->id() . ' (' . $source_uuid . ') ' . substr($entity->label(), 0, 40) . '... | ';
    }

    $this->logger->info($message);
  }

  /**
   * Mark entities as ignored from future migrations. Used as part of the
   * deletion process.
   *
   * @param array $entities
   *   The entities to process.
   */
  protected function markEntitiesAsIgnored(array $entities): void {
    foreach ($entities as $entity) {
      $entity_type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();

      if ($entity_type === 'media') {
        $table_name = 'migrate_map_d7_file_media_' . $bundle;
      }
      else {
        $table_name = 'migrate_map_' . $entity_type . '_' . $bundle;
      }

      $this->database->update($table_name)
        ->fields(['source_row_status' => MigrateIdMapInterface::STATUS_IGNORED])
        ->condition('destid1', $entity->id())
        ->execute();
    }
  }

}
