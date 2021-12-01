<?php

namespace Drupal\dept_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\example\ExampleInterface;

/**
 * DepartmentManager service.
 */
class DepartmentManager {

  /**
   * The domain.negotiator service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $domainNegotiator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a DepartmentManager object.
   *
   * @param \Drupal\example\ExampleInterface $domain_negotiator
   *   The domain.negotiator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ExampleInterface $domain_negotiator, EntityTypeManagerInterface $entity_type_manager) {
    $this->domainNegotiator = $domain_negotiator;
    $this->entityTypeManager = $entity_type_manager;
  }


}
