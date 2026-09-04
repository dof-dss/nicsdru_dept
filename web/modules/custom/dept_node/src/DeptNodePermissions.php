<?php

namespace Drupal\dept_node;

use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;

/**
 * Generates revision-delete permissions for each content type.
 */
class DeptNodePermissions {
  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /**
   * Returns the module's bundle-specific permissions.
   *
   * @return array
   *   The permissions keyed by machine name.
   */
  public function permissions(): array {
    return $this->generatePermissions(NodeType::loadMultiple(), [$this, 'buildPermissions']);
  }

  /**
   * Builds permissions for one content type.
   */
  protected function buildPermissions(NodeType $type): array {
    return [
      DeptNodeAccessControlHandler::revisionDeletePermission($type->id()) => [
        'title' => $this->t('%type_name: Delete non-current revisions without deleting content', [
          '%type_name' => $type->label(),
        ]),
      ],
    ];
  }

}
