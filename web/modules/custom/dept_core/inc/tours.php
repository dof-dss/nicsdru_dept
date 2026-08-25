<?php

/**
 * @file
 * Hooks and functions for altering Tours on departmental sites.
 */

/**
 * Implements hook_tour_tips_alter().
 */
function dept_core_tour_tips_alter(array &$tour_tips, EntityInterface $entity) {

  if ($entity->id() === 'origins_node_create') {
    $route_params = \Drupal::routeMatch()->getParameters();

    // Replace the default 'Title' text as these types don't use that label.
    switch ($route_params->get('node_type')->id()) {
      case 'contact':
        $body = $tour_tips['title']->get('body');
        $body = str_replace('title', 'Point of contact', $body);
        $tour_tips['title']->set('body', $body);
        break;

      case 'link':
        $body = $tour_tips['title']->get('body');
        $body = str_replace('title', 'Link text', $body);
        $tour_tips['title']->set('body', $body);
        break;

      default:
        break;
    }
  }
}
