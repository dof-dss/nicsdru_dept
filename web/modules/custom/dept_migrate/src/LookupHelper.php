<?php

namespace Drupal\dept_migrate;

class LookupHelper {

  protected $lookupManager;

  protected $id;

  protected $uuid;

  protected $direction;

  public function __construct(MigrateUuidLookupManager $lookup_manager) {
    $this->lookupManager = $lookup_manager;
  }

  public function id($id) {
    if ($this->direction == 'source') {
      return $this->lookupManager->lookupBySourceNodeId([$id]);
    } else {
      return $this->lookupManager->lookupByDestinationNodeIds([$id]);
    }
  }

  public function uuid($uuid) {
    if ($this->direction == 'source') {
      return $this->lookupManager->lookupBySourceUuId([$uuid]);
    } else {
      return $this->lookupManager->lookupByDestinationUuid([$uuid]);
    }
  }

  public function source() {
    $this->direction = 'source';
    return $this;
  }

  public function destination() {
    $this->direction = 'destination';
    return $this;
  }

}
