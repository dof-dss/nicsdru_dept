<?php

namespace Drupal\dept_topics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\dept_core\DepartmentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentStacksController.
 *
 * Provides an initial endpoint for managing stacks of ordered content
 * based on the department it is in. Deeper pages are managed as views
 * displays but as Department is a different entity type we can't mix that into
 * the same view's display configurations which is where this controller class
 * fits into the wider setup.
 */
class ContentStacksController extends ControllerBase {

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $t;

  /**
   * The department manager service object.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $deptManager;

  /**
   * Constructs a new controller instance.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\dept_core\DepartmentManager $dept_manager
   *   The department manager service.
   */
  public function __construct(TranslationInterface $string_translation, DepartmentManager $dept_manager) {
    $this->t = $string_translation;
    $this->deptManager = $dept_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('department.manager')
    );
  }

  /**
   * Show a list of departments with option to manage topics for them.
   *
   * @return array
   *   Render array.
   */
  public function default() {
    $build = [];

    $intro_text = "The links on this page allow administrators to manage to order of appearance for
      any topics within a department site. Deeper pages also permit ordering of subtopics within each topic,
      as well as the order of any articles in a subtopic.<br />
      <strong>Please note:</strong> adding and removing references between content
      is performed by using the relevant fields on a topic/subtopic or article node edit form.";
    $build['intro'] = [
      '#markup' => "<p>${intro_text}</p>",
    ];

    // Table header/sort options.
    $header = [
      'title' => [
        'data' => $this->t->translate('Department name'),
        'field' => 'title',
        'sort' => 'asc'
      ],
      'tasks' => $this->t->translate('Tasks'),
    ];

    $depts = $this->deptManager->getAllDepartments();

    $rows = [];

    if (!empty($depts)) {
      foreach ($depts as $dept) {
        /** @var \Drupal\dept_core\Department $dept */
        $dept_id = $dept->groupId();
        $dept_name = $dept->name();
        $dept_url = Url::fromUri($dept->url());

        $rows[] = [
          Link::fromTextAndUrl($dept_name, $dept_url),
          Link::createFromRoute($this->t->translate('Manage topics'), 'view.content_stacks.dept_topics', ['arg_0' => $dept_id]),
        ];
      }
    }

    $build['links_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t->translate('No departments found.'),
    ];

    return $build;
  }

}
