<?php

namespace Drupal\dept_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IndexController extends ControllerBase {

  /**
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

  public function __construct(MigrateUuidLookupManager $lookup_manager, TranslatorInterface $translator, PagerManagerInterface $pager_manager, PagerParametersInterface $pager_params) {
    $this->lookupManager = $lookup_manager;
    $this->t = $translator;
    $this->pagerManager = $pager_manager;
    $this->pagerParameters = $pager_params;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dept_migrate.migrate_uuid_lookup_manager'),
      $container->get('string_translation'),
      $container->get('pager.manager'),
      $container->get('pager.parameters')
    );
  }

  public function default() {
    $content = [];

    // Table header/sort options.
    $header = [
      'title' => [
        'data' => $this->t->translate('Title'),
        'field' => 'title',
        'sort' => 'asc'
      ],
      'type' => $this->t->translate('Type'),
      'fields' => $this->t->translate('From field(s)'),
      'tasks' => $this->t->translate('Tasks'),
    ];

    // Pager init.
    $page = $this->pagerParameters->findPage();
    $num_per_page = 25;
    $offset = $num_per_page * $page;

    $rows = [];

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

    return $content;
  }

}
