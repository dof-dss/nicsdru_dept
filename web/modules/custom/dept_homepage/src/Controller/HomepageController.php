<?php

namespace Drupal\dept_homepage\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for handling the site root path.
 *
 * This seemingly inconspicuous class responds to the default site route
 * callback and is intended to replace the Drupal default of '/node',
 * which is handled by a view and shows any content promoted to the front
 * page. This is bad news for two reasons:
 *
 * 1. The homepage is mostly comprised of blocks rendered into page regions.
 * 2. If a node is accidentally promoted to the front page using the
 *    'frontpage' view, then it will begin to inject rendered nodes in some
 *    view mode alongside the defined blocks, and disrupt the display of
 *    the homepage.
 *
 * So by keeping our controller here, responding with an empty render array,
 * we protect ourselves from this rather large volume of proverbial egg
 * destined for the face, and ensure that our blocks can render in the regions
 * without being bothered.
 */
class HomepageController extends ControllerBase {

  /**
   * Default callback.
   *
   * @return array
   *   Return a render array.
   */
  public function default() {
    $build = [];

    // Render a FCL node for the active domain.
    /** @var \Drupal\dept_core\DepartmentManager $dept */
    $dept = \Drupal::service('department.manager');
    $active_dept = $dept->getCurrentDepartment();

    $fcl_query = \Drupal::entityQuery('node')
      ->condition('type', 'featured_content_list')
      ->condition('status', 1)
      ->condition('field_domain_source', $active_dept->id())
      ->range(0, 1)
      ->accessCheck(TRUE)
      ->execute();

    $fcl_node = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($fcl_query);

    if (empty($fcl_node)) {
      return $build;
    }
    else {
      $fcl_node = reset($fcl_node);
    }

    // Create render element for the node.
    $fcl_render = \Drupal::entityTypeManager()
      ->getViewBuilder('node')
      ->view($fcl_node, 'full');

    $build['featured_news'] = [
      '#type' => 'html_tag',
      '#tag' => 'section',
      '#weight' => -1,
      '#attributes' => [
        'class' => [
          'section--featured-highlights',
          'section--featured section-front',
          'section-front--featured',
        ],
      ],
    ];
    $build['featured_news']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => t('Featured news'),
    ];
    $build['featured_news']['fcl'] = $fcl_render;

    return $build;
  }

}
