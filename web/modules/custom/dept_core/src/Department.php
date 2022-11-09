<?php

namespace Drupal\dept_core;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\Entity\Domain;

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
   * Hostnames for the department.
   *
   * @var array
   */
  protected array $hostnames;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage_sync
   *   The default 'sync' config storage.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The current (loaded) config storage.
   * @param string|null $domain_id
   *   The Domain Identifier.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StorageInterface $config_storage_sync, StorageInterface $config_storage, string $domain_id = NULL) {
    $this->id = $domain_id;
    $domain = $this->domain = $entity_type_manager->getStorage('domain')->load($this->id);

    if ($domain instanceof Domain) {
      $this->name = $domain->label();
      $this->domainId = $domain->get('domain_id');
      $this->setGroupId($domain->id());
      $this->group = $entity_type_manager->getStorage('group')->load($this->groupId);

      // Live Url for the department.
      $config = $config_storage_sync->read('domain.record.' . $this->id());
      $this->hostnames[] = $config['hostname'];

      // Current configuration URL for the department.
      $config = $config_storage->read('domain.record.' . $this->id());
      $this->hostnames[] = $config['hostname'];
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
      preg_match('/(\d+)/', $group_id, $matches);
      $this->groupId = $matches[0];
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
   * Full URL (protocol and hostname).
   *
   * @param bool $live_url
   *   Return live URL if true, else return the configuration Url.
   */
  public function url(bool $live_url = TRUE, bool $secure_protocol = TRUE): string {
    return ($secure_protocol ? "https://" : "http://") . $this->hostname($live_url);
  }

  /**
   * Hostname.
   *
   * @param boolean $live_hostname
   *   Return live hostname if true, else return the configuration hostname.
   */
  public function hostname($live_hostname = TRUE): string {
      return $live_hostname ? $this->hostnames[0] : $this->hostnames[1];
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
    return $this->group->field_management_and_structure->view();
  }

  /**
   * Access to information details.
   */
  public function accessToInformation() {
    return $this->group->field_access_to_information->view();
  }

  /**
   * Contact Information details.
   */
  public function contactInformation() {
    return $this->group->field_contact_information->view();
  }

  /**
   * Social media links.
   */
  public function socialMediaLinks() {
    return $this->group->field_social_media_links->view();
  }

  /**
   * Point of contact map location.
   */
  public function location() {
    return $this->group->field_location->view();
  }

  /**
   * Accessibility statement.
   */
  public function accessibilityStatement() {
    return (empty($this->group->field_accessibility_statement->referencedEntities())) ? NULL : $this->group->field_accessibility_statement->referencedEntities()[0];
  }

}
