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
  public function d7Id(): string|null {
    return $this->item['d7nid'] ?? NULL;
  }

  /**
   * Drupal 7 UUID.
   *
   * @return string
   *   UUID.
   */
  public function d7Uuid(): string|null {
    return $this->item['d7uuid'] ?? NULL;
  }

  /**
   * Drupal 10 entity ID.
   *
   * @return string
   *   Entity ID.
   */
  public function id(): string|null {
    return $this->item['nid'] ?? NULL;
  }

  /**
   * Drupal 10 entity UUID.
   *
   * @return string
   *   UUID.
   */
  public function uuid(): string|null {
    return $this->item['uuid'] ?? NULL;
  }

  /**
   * Bundle ID.
   *
   * @return string
   *   Bundle ID.
   */
  public function type(): string|null {
    return $this->item['type'] ?? NULL;
  }

  /**
   * Domains for the entry.
   *
   * @return mixed
   *   Array of domain ids.
   */
  public function domains(): array|null {
    return $this->item['domains'] ?? NULL;
  }

}
