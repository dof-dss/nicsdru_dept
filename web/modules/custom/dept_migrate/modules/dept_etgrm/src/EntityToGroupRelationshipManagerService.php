<?php

namespace Drupal\dept_etgrm;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * EntityToGroupRelationshipManagerService service.
 */
class EntityToGroupRelationshipManagerService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Constructs an EntityToGroupRelationshipManagerService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

}
