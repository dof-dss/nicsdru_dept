<?php

namespace Drupal\dept_homepage\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\dept_core\DepartmentManager;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for handling the site root path.
 */
final class HomepageController extends ControllerBase {

  protected NodeStorageInterface $nodeStorage;

  public function __construct(
    protected DepartmentManager $deptManager,
    protected $entityTypeManager,
  ) {
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('department.manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Default callback.
   *
   * @return array
   *   Return a render array.
   */
  public function default(): array {
    $build = [];

    $active_dept = $this->deptManager->getCurrentDepartment();
    if ($active_dept === NULL) {
      return $build;
    }

    $fcl_ids = $this->nodeStorage->getQuery()
      ->condition('type', 'featured_content_list')
      ->condition('status', 1)
      ->condition('field_domain_source', $active_dept->id())
      ->range(0, 1)
      ->accessCheck(TRUE)
      ->execute();

    if (empty($fcl_ids)) {
      return $build;
    }

    $fcl_node = $this->nodeStorage->load(reset($fcl_ids));
    if (!$fcl_node) {
      return $build;
    }

    $fcl_render = $this->entityTypeManager
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
        'tags' => ['homepage_featured:' . $active_dept->id()],
      ],
    ];

    $build['featured_news']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Featured'),
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

    if ($current_department === NULL) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('No current department could be determined.'),
      ];
    }

    // Replace \Drupal::entityQuery('node') with storage query.
    $results = $this->nodeStorage->getQuery()
      ->condition('type', 'featured_content_list')
      ->condition('field_domain_source', $current_department->id())
      ->range(0, 1)
      ->accessCheck(TRUE)
      ->execute();

    if (empty($results)) {
      return [
        'intro' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('The current department does not have any featured content.'),
        ],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Create featured content'),
          '#url' => Url::fromRoute('node.add', ['node_type' => 'featured_content_list']),
        ],
      ];
    }

    return $this->redirect('entity.node.edit_form', ['node' => (int) reset($results)]);
  }

}
