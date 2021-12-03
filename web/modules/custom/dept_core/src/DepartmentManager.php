<?php

namespace Drupal\dept_core;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\example\ExampleInterface;

/**
 * DepartmentManager service.
 */
class DepartmentManager {

  /**
   * The domain.negotiator service.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $domainNegotiator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The default cache bin service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a DepartmentManager object.
   *
   * @param \Drupal\domain\DomainNegotiatorInterface $domain_negotiator
   *   The Domain negotiator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(DomainNegotiatorInterface $domain_negotiator, EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache) {
    $this->domainNegotiator = $domain_negotiator;
    $this->entityTypeManager = $entity_type_manager;
    $this->cache = $cache;
  }

  public function getCurrentDepartment() {
    $active_domain = $this->domainNegotiator->getActiveDomain();

    $department = $this->cache->get('department_' . $active_domain->id());

    if (empty($department)) {
     $department = new Department($this->entityTypeManager, $active_domain->id());
     $this->cache->set('department_' . $active_domain->id(), $department);
    }

    return $department;
  }

  public function getDepartment($id) {
    return new Department($id);
  }
}
