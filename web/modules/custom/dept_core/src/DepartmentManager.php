<?php

namespace Drupal\dept_core;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;

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

    return $this->getDepartment($active_domain->id());
  }

  /**
   * Returns a department.
   * @param $id
   *   The domain ID to load a department.
   */
  public function getDepartment($id) {
    $department = $this->cache->get('department_' . $id)->data;

    if (empty($department)) {
      $department = new Department($this->entityTypeManager, $id);
      // Add to cache and use tags that will invalidate when the Domain or Group entities change.
      $this->cache->set('department_' . $id, $department, CACHE::PERMANENT, ['url.site','group:' . $department->getGroupId()]);
    }

    return $department;
  }


}
