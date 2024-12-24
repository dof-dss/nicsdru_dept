<?php

namespace Drupal\dept_homepage\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dept_core\DepartmentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $deptManager;

  /**
   * Constructor for controller class.
   *
   * @param \Drupal\dept_core\DepartmentManager $dept_manager
   *   Department manager service object.
   */
  public function __construct(DepartmentManager $dept_manager) {
    $this->deptManager = $dept_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('department.manager')
    );
  }

  /**
   * Default callback.
   *
   * @return array
   *   Return a render array.
   */
  public function default() {
    $build = [];
    $node_storage = $this->entityTypeManager()->getStorage('node');

    // Render a FCL node for the active domain.
    $active_dept = $this->deptManager->getCurrentDepartment();

    if (is_null($active_dept)) {
      return $build;
    }

    $fcl_query = $node_storage->getQuery()
      ->condition('type', 'featured_content_list')
      ->condition('status', 1)
      ->condition('field_domain_source', $active_dept->id())
      ->range(0, 1)
      ->accessCheck(TRUE)
      ->execute();

    $fcl_node = $node_storage->loadMultiple($fcl_query);

    if (empty($fcl_node)) {
      return $build;
    }
    else {
      $fcl_node = reset($fcl_node);
    }

    // Create render element for the node.
    $fcl_render = $this->entityTypeManager()
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
      '#cache' => [
        'tags' => ['featured:' . $active_dept->id()],
      ],
    ];
    $build['featured_news']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => t('Featured'),
    ];
    $build['featured_news']['fcl'] = $fcl_render;

    return $build;
  }

  /**
   * Redirect to the Featured Content node edit form for the current department.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   Return a redirect or render array.
   */
  public function featuredContentEdit() {
    $current_department = $this->deptManager->getCurrentDepartment();

    $query = \Drupal::entityQuery('node')
      ->condition('type', 'featured_content_list')
      ->condition('field_domain_source', $current_department->id())
      ->range(0, 1)
      ->accessCheck(TRUE);
    $results = $query->execute();

    if (empty($results)) {
      return [
        'intro' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => 'The current department does not have any featured content.'
        ],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Create featured content'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'featured_content_list']),
        ],
      ];
    }
    else {
      return $this->redirect('entity.node.edit_form', ['node' => current($results)]);
    }
  }

}
