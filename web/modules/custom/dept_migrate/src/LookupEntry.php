<?php

namespace Drupal\dept_migrate;

class LookupEntry {

  /**
   * Lookup data.
   *
   * @var false|mixed
   */
  protected $item;

  public function __construct(array $lookup_item) {
    $this->item = current($lookup_item);
  }

  /**
   * Drupal 7 entity ID.
   *
   * @return string
   *   Entity ID.
   */
  public function d7Id(): string {
    return $this->item['d7nid'];
  }

  /**
   * Drupal 7 UUID.
   *
   * @return string
   *   UUID.
   */
  public function d7Uuid(): string {
    return $this->item['d7uuid'];
  }

  /**
   * Drupal 10 entity ID.
   *
   * @return string
   *   Entity ID.
   */
  public function id(): string {
    return $this->item['nid'];
  }

  /**
   * Drupal 10 entity UUID.
   *
   * @return string
   *   UUID.
   */
  public function uuid(): string {
    return $this->item['uuid'];
  }

  /**
   * Bundle ID.
   *
   * @return string
   *   Bundle ID.
   */
  public function type(): string {
    return $this->item['type'];
  }

  /**
   * Domains for the entry.
   *
   * @return mixed
   *   Array of domain ids.
   */
  public function domains() {
    return $this->item['domains'];
  }

}
