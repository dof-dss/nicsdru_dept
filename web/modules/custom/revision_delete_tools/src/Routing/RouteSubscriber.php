<?php

namespace Drupal\revision_delete_tools\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.node.version_history');
    if ($route && \Drupal::configFactory()->get('revision_delete_tools.configuration')->get('bulk_delete')) {
      // Unset the Core controller for node.revision_overview and replace with
      // a custom form than we can then override to insert the bulk actions.
      if ($route->getDefault('_controller') === '\Drupal\node\Controller\NodeController::revisionOverview') {
        $defaults = $route->getDefaults();
        unset($defaults['_controller']);
        $defaults['_form'] = '\Drupal\revision_delete_tools\Form\NodeRevisionsForm';
        $route->setDefaults($defaults);
      }
    }
  }

}
