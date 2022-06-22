<?php

namespace Drupal\dept_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class NodeDetailController extends ControllerBase {

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * Migration lookup manager service.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * Drupal\Core\StringTranslation\Translator\TranslatorInterface definition.
   *
   * @var \Drupal\Core\StringTranslation\Translator\TranslatorInterface
   */
  protected $t;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7conn;

  /**
   * {@inheritdoc}
   */
  public function __construct(FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager, RequestStack $request, MigrateUuidLookupManager $lookup_manager, TranslatorInterface $translator) {
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request;
    $this->lookupManager = $lookup_manager;
    $this->t = $translator;

    $this->dbconn = Database::getConnection('default', 'default');
    $this->d7conn = Database::getConnection('default', 'migrate');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('dept_migrate.migrate_uuid_lookup_manager'),
      $container->get('string_translation')
    );
  }

  /**
   * Callback for migration index display.
   *
   * @return array
   *   Render array.
   */
  public function default() {
    $content['preamble'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t->translate('Enter a node ID (Drupal 9) to see
        the contents from the relevant migration/group tables.'),
    ];

    $query_params = $this->request->getCurrentRequest()->query;
    $nid = $query_params->get('nid') ?? '';

    $content['filter_form'] = $this->formBuilder->getForm('Drupal\dept_migrate\Form\NodeDetailFilterForm');

    $rows__group_content_field_data = [];
    $rows__group_content = [];
    $rows__node_access = [];
    $rows__migrate_map = [];

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if ($node instanceof NodeInterface === FALSE) {
      $content['no_results'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t->translate('No node found.'),
      ];
    }
    else {
      $content['breaker'] = [
        '#type' => 'html_tag',
        '#tag' => 'hr',
        '#attributes' => [
          'style' => 'margin: 2em 0',
        ],
      ];

      // Table for: group_content_field_data.
      $results = $this->dbconn->query('SELECT * FROM {group_content_field_data} WHERE entity_id = :id', [':id' => $nid])
        ->fetchAll();
      if (!empty($results)) {
        foreach ($results as $idx => $row) {
          // Add detail to array for table.
          $rows__group_content_field_data[$row->id] = $row;
        }
      }

      // Table for: group_content.
      // Get rows for group_content.
      $ids = array_keys($rows__group_content_field_data);
      $results = $this->dbconn->query("SELECT * FROM {group_content} WHERE id IN (:ids[])", [':ids[]' => $ids])
        ->fetchAll();
      if (!empty($results)) {
        foreach ($results as $idx => $row) {
          $rows__group_content[] = $row;
        }
      }

      // Table for: node_access.
      $results = $this->dbconn->query('SELECT * FROM {node_access} WHERE nid = :nid', [':nid' => $nid])
        ->fetchAll();
      if (!empty($results)) {
        foreach ($results as $idx => $row) {
          $rows__node_access[] = $row;
        }
      }

      // Table for: migrate_map_node_<type>.
      $table = 'migrate_map_node_' . $node->bundle();

      $results = $this->dbconn->query("SELECT * FROM ${table} WHERE destid1 = :nid", [':nid' => $nid])
        ->fetchAll();
      if (!empty($results)) {
        foreach ($results as $idx => $row) {
          $rows__migrate_map[] = $row;
        }
      }

      // Render elements for tables.
      $content['node'] = [
        '#type' => 'fieldset',
        '#title' => $this->t->translate('Node information'),
      ];
      $content['node']['detail'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t->translate(':type: :title', [
          ':type' => $node->type->entity->label(),
          ':title' => $node->label(),
        ]),
      ];

      foreach ([
                 'group_content' => $rows__group_content,
                 'group_content_field_data' => array_values($rows__group_content_field_data),
                 'node_access' => $rows__node_access,
                 'migrate_map' => $rows__migrate_map
               ] as $name => $table_data) {

        $header = [];
        $rows = [];

        if (!empty($table_data)) {
          foreach ($table_data[0] as $colname => $row_value) {
            $header[] = $colname;
            $rows[] = $row_value;
          }
        }

        $content[$name] = [
          '#type' => 'fieldset',
          '#title' => $name,
        ];
        $content[$name]['table'] = [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => (empty($rows)) ? [] : ['data' => $rows],
          '#empty' => $this->t->translate('No data.'),
        ];
      }
    }

    return $content;
  }

}
