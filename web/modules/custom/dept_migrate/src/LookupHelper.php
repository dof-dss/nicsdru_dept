<?php

namespace Drupal\dept_migrate;

class LookupHelper {

  /**
   * Lookup manager service.
   *
   * @var \Drupal\dept_migrate\MigrateUuidLookupManager
   */
  protected $lookupManager;

  /**
   * The data source to lookup.
   *
   * @var string
   */
  protected $direction;

  /**
   * Constructor.
   *
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookup_manager
   *   Lookup manager service.
   */
  public function __construct(MigrateUuidLookupManager $lookup_manager) {
    $this->lookupManager = $lookup_manager;
  }

  /**
   * Lookup result using entity ID.
   *
   * @param string $id
   *   Entity ID value.
   *
   * @return \Drupal\dept_migrate\LookupEntry
   *   LookupEntry for the given id.
   */
  public function id($id) {
    if ($this->direction == 'source') {
      return new LookupEntry($this->lookupManager->lookupBySourceNodeId([$id]));
    }
    else {
      return new LookupEntry($this->lookupManager->lookupByDestinationNodeIds([$id]));
    }
  }

  /**
   * Lookup result using UUID.
   *
   * @param string $uuid
   *   UUID value.
   *
   * @return \Drupal\dept_migrate\LookupEntry
   *   LookupEntry for the given uuid.
   */
  public function uuid($uuid) {
    if ($this->direction == 'source') {
      return new LookupEntry($this->lookupManager->lookupBySourceUuId([$uuid]));
    }
    else {
      return new LookupEntry($this->lookupManager->lookupByDestinationUuid([$uuid]));
    }
  }

  /**
   * Lookup result using source (migration) database.
   *
   * @return $this
   */
  public function source() {
    $this->direction = 'source';
    return $this;
  }

  /**
   * Lookup result using destination (site) database.
   *
   * @return $this
   */
  public function destination() {
    $this->direction = 'destination';
    return $this;
  }

}
