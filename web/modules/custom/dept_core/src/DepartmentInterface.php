<?php

namespace Drupal\dept_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a department entity type.
 */
interface DepartmentInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
