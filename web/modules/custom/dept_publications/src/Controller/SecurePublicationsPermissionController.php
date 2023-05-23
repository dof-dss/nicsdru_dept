<?php

namespace Drupal\dept_publications\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\origins_workflow\Controller\ModerationStateController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles permissions callbacks for changing moderation state on
 * secure publication nodes.
 */
class SecurePublicationsPermissionController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Controller resolver service.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * Constructor for this controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service object.
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver
   *   Controller resolver service object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ControllerResolverInterface $controller_resolver) {
    $this->entityTypeManager = $entity_type_manager;
    $this->controllerResolver = $controller_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('controller_resolver')
    );
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account object.
   * @param int $nid
   *   Then node id.
   * @param string $new_state
   *   A string machine name of the new moderation state, eg: 'published'.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessCheckModerationStateChange(AccountInterface $account, int $nid, string $new_state) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    // If this is a publication node with secure attachment field value set
    // then evaluate an extra permission first, before publishing.
    if ($node->getType() === 'publication' && !empty($node->get('field_publication_secure_files')->referencedEntities())) {
      if ($new_state === 'published') {
        $publish_permission = AccessResult::allowedIfHasPermissions($account, [
          'use nics_editorial_workflow transition publish',
          'publish secure publication',
        ]);

        return $publish_permission;
      }
    }

    $controller_lookup = $this->controllerResolver
      ->getControllerFromDefinition('\Drupal\origins_workflow\Controller\ModerationStateController::changeState');
    $origins_controller = $controller_lookup[0];

    if ($origins_controller instanceof ModerationStateController) {
      $origins_controller->changeState($nid, $new_state);
      // Mirrors permission defined in origins_workflow.routing.yml.
      return AccessResult::allowedIfHasPermission($account, 'access content');
    }

    // If nothing else, return a neutral opinion for access control.
    return AccessResult::neutral();
  }

}
