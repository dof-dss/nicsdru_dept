<?php

namespace Drupal\dept_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Class for bridging the gap between Domain and Group entities for Departments.
 *
 * This class should not be used to load Departments directly, instead the
 * methods available in the DepartmentManager service should be used.
 */
class Department {

  /**
   * The Departments Group entity.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  private $group;

  /**
   * The Departments Domain entity.
   *
   * @var \Drupal\domain\Entity\Domain
   */
  private $domain;

  /**
   * The Group identifier.
   *
   * @var int
   */
  protected int $groupId;

  /**
   * The Domain identifier.
   *
   * @var int
   */
  protected int $domainId;

  /**
   * The Department identifier.
   *
   * @var string
   */
  protected string $id;

  /**
   * The name of the Department.
   *
   * @var string
   */
  protected string $name;

  /**
   * URL for the department.
   *
   * @var string
   */
  protected $url;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param string $domain_id
   *   The Domain Identifier.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, string $domain_id = NULL) {
    $this->id = $domain_id;
    $this->domain = $entity_type_manager->getStorage('domain')->load($this->id);

    if (!empty($this->domain)) {
      $this->name = $this->domain->label();
      $this->url = $this->domain->getPath();
      $this->domainId = $this->domain->get('domain_id');
      $this->setGroupId($this->domain->id());
      $this->group = $entity_type_manager->getStorage('group')->load($this->groupId);
    }
  }

  /**
   * Group Identifier.
   */
  public function groupId(): string {
    return $this->groupId;
  }

  /**
   * Setter for Group Identifier.
   *
   * @param mixed $group_id
   *   The Group Identifier.
   */
  protected function setGroupId($group_id): void {
    if (is_int($group_id)) {
      $this->groupId = $group_id;
    }
    else {
      $this->groupId = substr($group_id, strpos($group_id, "_") + 1);
    }
  }

  /**
   * Domain Identifier.
   */
  public function domainId() {
    return $this->domainId;
  }

  /**
   * Departmental Identifier.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * Name.
   */
  public function name(): string {
    return $this->name;
  }

  /**
   * URL.
   */
  public function url(): string {
    return $this->url;
  }

  /**
   * User account for the Department.
   */
  public function getMember(AccountInterface $account) {
    return $this->group->getMember($account);
  }

  /**
   * User accounts belonging to the Department.
   */
  public function getMembers() {
    return $this->group->getMembers();
  }

  /**
   * Management and Structure details.
   */
  public function managementAndStructure() {
    return render($this->group->field_management_and_structure->view('full'));
  }

  /**
   * Access to information details.
   */
  public function accessToInformation() {
    return render($this->group->field_access_to_information->view('full'));
  }

  /**
   * Contact Information details.
   */
  public function contactInformation() {
    return render($this->group->field_contact_information->view('full'));
  }

  /**
   * Social media links.
   */
  public function socialMediaLinks() {
    return render($this->group->field_social_media_links->view('full'));
  }

}
