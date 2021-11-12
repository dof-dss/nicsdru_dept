<?php

namespace Drupal\dept_core;

/**
 * Provides an interface defining a Group content entity.
 */
interface GroupContentEntityInterface {

  /**
   * Gets the Group bundle of the entity.
   *
   * @return string
   *   The bundle of the entity. Defaults to the entity type ID if the entity
   *   type does not make use of different bundles.
   */
  public function groupBundle();

  /**
   * Returns the Groups the entity is related to.
   *
   * @return array
   *   An array of Groups. Group ID as key and title as value.
   */
  public function getGroups();

}
