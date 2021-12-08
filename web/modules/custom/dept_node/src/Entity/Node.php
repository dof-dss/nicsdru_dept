<?php

namespace Drupal\dept_node\Entity;

use Drupal\dept_core\GroupableEntityTrait;
use Drupal\dept_core\GroupContentEntityInterface;
use Drupal\node\Entity\Node as NodeBase;

/**
 * Node entity which integrates with Group module.
 */
class Node extends NodeBase implements GroupContentEntityInterface {

  use GroupableEntityTrait;

}
