<?php

namespace Drupal\dept_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class IndexController extends ControllerBase {

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

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
   * Drupal\Core\Pager\PagerManagerInterface definition.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Drupal\Core\Pager\PagerParametersInterface definition.
   *
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  protected $pagerParameters;

  /**
   * {@inheritdoc}
   */
  public function __construct(FormBuilderInterface $form_builder, RequestStack $request, MigrateUuidLookupManager $lookup_manager, TranslatorInterface $translator, PagerManagerInterface $pager_manager, PagerParametersInterface $pager_params) {
    $this->formBuilder = $form_builder;
    $this->request = $request;
    $this->lookupManager = $lookup_manager;
    $this->t = $translator;
    $this->pagerManager = $pager_manager;
    $this->pagerParameters = $pager_params;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('request_stack'),
      $container->get('dept_migrate.migrate_uuid_lookup_manager'),
      $container->get('string_translation'),
      $container->get('pager.manager'),
      $container->get('pager.parameters')
    );
  }

  /**
   * Callback for migration index display.
   *
   * @return array
   *   Render array.
   */
  public function default() {
    $content = [];
    $query_params = $this->request->getCurrentRequest()->query;

    $filter_type = $query_params->get('filter_type') ?? '';
    $filter_value = $query_params->get('filter_value') ?? '';
    $criteria[$filter_type] = $filter_value;

    $content['filter_form'] = $this->formBuilder->getForm('Drupal\dept_migrate\Form\MigrateIndexFilterForm');

    // Table header/sort options.
    $header = [
      'uuid' => $this->t->translate('UUID'),
      'nid' => $this->t->translate('Node ID'),
      'title' => [
        'data' => $this->t->translate('Title'),
        'field' => 'title',
        'sort' => 'asc'
      ],
      'type' => $this->t->translate('Type'),
      'd7nid' => $this->t->translate('Drupal 7 Node ID'),
      'd7uuid' => $this->t->translate('Drupal 7 Node UUID'),
      'tasks' => $this->t->translate('Tasks'),
    ];

    // Pager init.
    $page = $this->pagerParameters->findPage();
    $num_per_page = 25;
    $offset = $num_per_page * $page;

    // Fetch migration content data.
    $mig_data = $this->lookupManager->getMigrationContent($criteria, $num_per_page, $offset);

    // Now that we have the total number of results, initialize the pager.
    $this->pagerManager->createPager($mig_data['total'], $num_per_page);

    $rows = [];

    // Populate rows.
    if (!empty($mig_data['rows'])) {
      foreach ($mig_data['rows'] as $item) {
        $rows[] = [
          $item['uuid'],
          $item['nid'],
          Link::createFromRoute($item['title'], 'entity.node.canonical', ['node' => $item['nid']]),
          $item['type'],
          $item['d7nid'],
          $item['d7uuid'],
          Link::createFromRoute($this->t->translate('Edit'), 'entity.node.edit_form', ['node' => $item['nid']]),
        ];
      }
    }

    $content['index_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t->translate('No content available.'),
    ];

    $content['pager'] = [
      '#type' => 'pager',
    ];

    return $content;
  }

}
