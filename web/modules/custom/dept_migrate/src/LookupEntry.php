<?php

namespace Drupal\dept_migrate;

class LookupEntry {

  protected $item;

  protected $id;

  protected $uuid;

  public function __construct(array $lookup_item) {
      $this->item = current($lookup_item);
  }

  public function d7_id() {
    return $this->item['d7nid'];
  }

  public function d7_uuid() {
    return $this->item['d7uuid'];
  }

  public function id() {
    return $this->item['nid'];
  }

  public function uuid() {
    return $this->item['uuid'];
  }

  public function type() {
    return $this->item['type'];
  }

  public function domains() {
    return $this->item['domains'];
  }
}
