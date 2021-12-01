<?php

namespace Drupal\dept_core;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;

class Department {


  /**
   * @var \Drupal\group\Entity\GroupInterface
   */
  private $group;

  /**
   * @var \Drupal\domain\Entity\Domain
   */
  private $domain;

  protected $group_id;
  protected $domain_id;
  protected $name;
  protected $status;
  protected $url;

  /**
   *
   * @param $domain_id
   */
  public function __construct($domain_id) {
    $this->domain_id = $domain_id;

    $entity_type_manager = \Drupal::entityTypeManager();
    $this->domain = $entity_type_manager->getStorage('domain')->load($this->domain_id);

    if (!empty($this->domain)) {

      $this->setName($this->domain->label());
      $this->setUrl($this->domain->get('url'));
      $this->setGroupId($this->domain->id());

      $this->group = $entity_type_manager->getStorage('group')->load($this->group_id);
    }


  }

  /**
   * @return string
   */
  public function getGroupId(): string {
    return $this->group_id;
  }

  /**
   * @param mixed $group_id
   */
  public function setGroupId($group_id): void {
    if (is_int($group_id)) {
      $this->group_id = $group_id;
    } else {
      $this->group_id = substr($group_id, strpos($group_id, "_") + 1);
    }
  }

  /**
   * @return string
   */
  public function getDomainId(): string {
    return $this->domain_id;
  }

  /**
   * @param string $domain_id
   */
  public function setDomainId(string $domain_id): void {
    $this->domain_id = $domain_id;
  }

  /**
   * @return string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * @param mixed $name
   */
  public function setName($name): void {
    $this->name = $name;
  }

  /**
   * @return mixed
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * @param mixed $status
   */
  public function setStatus($status): void {
    $this->status = $status;
  }

  /**
   * @return string
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * @param string $url
   */
  public function setUrl(string $url): void {
    $this->url = $url;
  }

  public function getMember(AccountInterface $account) {
    return $this->group->getMember($account);
  }

  public function getMembers() {
    return $this->group->getMembers();
  }

  public function getManagementAndStructure() {
    return $this->group->get('field_management_and_structure')->getString();
  }


}
