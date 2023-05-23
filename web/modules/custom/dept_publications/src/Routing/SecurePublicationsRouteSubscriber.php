<?php

namespace Drupal\dept_publications\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class SecurePublicationsRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('origins_workflow.moderation_state_controller_change_state')) {
      $route->setRequirements([
        '_custom_access' => '\Drupal\dept_publications\Controller\SecurePublicationsPermissionController::accessCheckModerationStateChange',
      ]);
    }
  }

}
