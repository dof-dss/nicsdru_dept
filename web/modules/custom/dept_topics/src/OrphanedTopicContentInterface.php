<?php

declare(strict_types=1);

namespace Drupal\dept_topics;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an orphaned topic content entity type.
 */
interface OrphanedTopicContentInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
