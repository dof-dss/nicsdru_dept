<?php

namespace Drupal\dept_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Department sites: migration routes.
 */
class EntityReferenceController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The pager.manager service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $pagerManager;

  /**
   * The pager.parameters service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $pagerParameters;

  /**
   * The dept_migrate.migrate_uuid_lookup_manager service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $deptMigrateMigrateUuidLookupManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\example\ExampleInterface $pager_manager
   *   The pager.manager service.
   * @param \Drupal\example\ExampleInterface $pager_parameters
   *   The pager.parameters service.
   * @param \Drupal\example\ExampleInterface $dept_migrate_migrate_uuid_lookup_manager
   *   The dept_migrate.migrate_uuid_lookup_manager service.
   */
  public function __construct(FormBuilderInterface $form_builder, RequestStack $request_stack, PagerManagerInterface $pager_manager, PagerParametersInterface $pager_parameters, MigrateUuidLookupManager $dept_migrate_migrate_uuid_lookup_manager) {
    $this->formBuilder = $form_builder;
    $this->requestStack = $request_stack;
    $this->pagerManager = $pager_manager;
    $this->pagerParameters = $pager_parameters;
    $this->deptMigrateMigrateUuidLookupManager = $dept_migrate_migrate_uuid_lookup_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('request_stack'),
      $container->get('pager.manager'),
      $container->get('pager.parameters'),
      $container->get('dept_migrate.migrate_uuid_lookup_manager')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('Entity references'),
    ];

    return $build;
  }

}
