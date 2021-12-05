<?php

namespace Drupal\dept_core;

use Drupal\Core\Session\AccountInterface;

/**
 * Class for bridging the gap between Domain and Group entities for Departments.
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
   * Department published status.
   *
   * @var bool
   */
  protected bool $status;

  /**
   * URL for the department.
   *
   * @var string
   */
  protected $url;

  /**
   * Class constructor.
   */
  public function __construct($entity_type_manager, $domain_id = NULL) {
    $this->id = $domain_id;

    $this->domain = $entity_type_manager->getStorage('domain')->load($this->id);

    if (!empty($this->domain)) {
      $this->setName($this->domain->label());
      $this->setUrl($this->domain->get('url'));
      $this->setGroupId($this->domain->id());
      $this->setDomainId($this->domain->get('domain_id'));

      $this->group = $entity_type_manager->getStorage('group')->load($this->groupId);
    }
  }

  /**
   * Getter for Domain ID.
   */
  public function getGroupId(): string {
    return $this->groupId;
  }

  /**
   * Setter for Group ID.
   */
  public function setGroupId($groupId): void {
    if (is_int($groupId)) {
      $this->groupId = $groupId;
    }
    else {
      $this->groupId = substr($groupId, strpos($groupId, "_") + 1);
    }
  }

  /**
   * Getter for Domain ID.
   */
  public function getDomainId() {
    return $this->domainId;
  }

  /**
   * Setter for Domain ID.
   */
  public function setDomainId($domainId): void {
    $this->domainId = $domainId;
  }

  /**
   * Getter for ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Setter for ID.
   */
  public function setId(string $id): void {
    $this->id = $id;
  }

  /**
   * Getter for Name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Setter for Name.
   */
  public function setName($name): void {
    $this->name = $name;
  }

  /**
   * Getter for Status.
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * Setter for Status.
   */
  public function setStatus($status): void {
    $this->status = $status;
  }

  /**
   * Getter for URL.
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Setter for URL.
   */
  public function setUrl(string $url): void {
    $this->url = $url;
  }

  /**
   * Returns a User account for the Department.
   */
  public function getMember(AccountInterface $account) {
    return $this->group->getMember($account);
  }

  /**
   * Returns User accounts belonging to the Department.
   */
  public function getMembers() {
    return $this->group->getMembers();
  }

  /**
   * Returns Management and Structure details.
   */
  public function getManagementAndStructure() {
    return $this->group->get('field_management_and_structure')->getString();
  }

}
