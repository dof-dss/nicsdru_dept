<?php

/**
 * @file
 * Hooks and functions for altering Tours on departmental sites.
 */

use Drupal\Core\Entity\EntityInterface;

/**
 * Implements hook_tour_tips_alter().
 */
function dept_core_tour_tips_alter(array &$tour_tips, EntityInterface $entity) {

  if ($entity->id() === 'origins_node_create') {
    $route_params = \Drupal::routeMatch()->getParameters();

    if ($route_params->has('node_type')) {
      $bundle = $route_params->get('node_type');

      if (empty($bundle)) {
        return;
      }

      $title_tip = NULL;

      // Iterate the tour tips to find a 'title' tip.
      foreach ($tour_tips as $tour_tip) {
        if ($tour_tip->id() === 'title') {
          $title_tip = $tour_tip;
          break;
        }
      }

      if ($title_tip === NULL) {
        return;
      }

      // Replace the default 'Title' text as these types don't use that label.
      switch ($bundle->id()) {
        case 'contact':
          $body = str_replace('title', 'Point of contact', $title_tip->get('body'));
          $title_tip->set('body', $body);
          break;

        case 'link':
          $body = str_replace('title', 'Link text', $title_tip->get('body'));
          $title_tip->set('body', $body);
          break;

        default:
          break;
      }
    }
  }
}
