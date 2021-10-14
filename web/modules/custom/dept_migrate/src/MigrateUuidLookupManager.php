<?php

namespace Drupal\dept_migrate;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to help build a map between D7 legacy content
 * and migrated D9 import, based on D7 UUID or node id lookups.
 */
class MigrateUuidLookupManager {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Constructs a new instance of this object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function lookupBySourceNodeId(array $nids) {
    return [
      'source' => [
        'nid' => '123',
        'title' => 'Placeholder',
      ],
      'destination' => [
        'nid' => '987',
        'title' => 'Placeholder',
      ],
    ];
  }

  public function lookupByDestinationUuid(array $uuids) {

  }

}
