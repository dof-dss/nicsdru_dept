<?php

namespace Drupal\dept_dev\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for dept_dev routes.
 */
class LandoHostnamesController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function setLandHostnames() {
    $domains = $this->entityTypeManager->getStorage('domain')->loadMultiple();

    foreach ($domains as $domain) {
      $hostname = $domain->getHostname();
      $hostname = substr_replace($hostname, '.lndo.site', strpos($hostname, '.'));
      $domain->setHostname($hostname);
      $domain->set('scheme', 'http');
      $domain->save();
    }

    return $this->redirect('domain.admin');
  }



}
