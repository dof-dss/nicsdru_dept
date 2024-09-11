<?php

namespace Drupal\dept_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\domain\DomainNegotiatorInterface;

/**
 * Service class for managing Department objects.
 */
final class DepartmentManager {

  /**
   * Constructs a DepartmentManager object.
   *
   * @param \Drupal\domain\DomainNegotiatorInterface $domainNegotiator
   *   The Domain negotiator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager service.
   */
  public function __construct(
    protected DomainNegotiatorInterface $domainNegotiator,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Returns the Department for the current domain.
   *
   * @return \Drupal\dept_core\Entity\Department
   *   The Department entity.
   */
  public function getCurrentDepartment() {
    $active_domain = $this->domainNegotiator->getActiveDomain();

    return $this->getDepartment($active_domain->id());
  }

  /**
   * Returns all Departments as an array of objects.
   *
   * @return array
   *   An array of Department entities.
   */
  public function getAllDepartments() {
    return $this->entityTypeManager->getStorage('department')->loadMultiple();
  }

  /**
   * Returns a department.
   *
   * @param string $id
   *   The department ID to load.
   *
   * @return \Drupal\dept_core\Entity\Department
   *   The Department entity.
   */
  public function getDepartment(string $id) {
    // Ignore the site administration domain.
    if ($id === 'dept_admin') {
      return NULL;
    }

    return $this->entityTypeManager->getStorage('department')->load($id);
  }

}
