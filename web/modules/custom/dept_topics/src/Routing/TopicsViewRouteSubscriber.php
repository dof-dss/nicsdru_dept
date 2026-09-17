<?php

namespace Drupal\dept_topics\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters topic routes.
 */
class TopicsViewRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('view.topics.topics_page')) {
      $route->setPath('/topics');
      $route->setOption('_view_argument_map', []);
    }
  }

}
