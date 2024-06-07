<?php

namespace Drupal\dept_redirects\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManager;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;

class RedirectCheckerController extends ControllerBase {

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManager
   */
  protected $pagerManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new RedirectCheckerController object.
   *
   * @param \Drupal\Core\Pager\PagerManager $pager_manager
   *   The pager manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(PagerManager $pager_manager, FormBuilderInterface $form_builder) {
    $this->pagerManager = $pager_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pager.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * Display the redirect check form and results.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   Render array.
   */
  public function checkRedirects(Request $request) {
    $form = $this->formBuilder->getForm('Drupal\dept_redirects\Form\RedirectCheckForm');

    // Fetch the query parameters for filtering.
    $source_filter = $request->query->get('source', '');
    $destination_filter = $request->query->get('destination', '');

    // Build the query to fetch results.
    $connection = Database::getConnection();
    $query = $connection->select('dept_redirects_results', 'd')
      ->fields('d', ['id', 'source', 'destination', 'status', 'checked']);

    // Apply filters if provided.
    if (!empty($source_filter)) {
      $query->condition('d.source', '%' . $connection->escapeLike($source_filter) . '%', 'LIKE');
    }
    if (!empty($destination_filter)) {
      $query->condition('d.destination', '%' . $connection->escapeLike($destination_filter) . '%', 'LIKE');
    }

    $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(25);
    $results = $query->execute();

    // Build the table header.
    $header = [
      ['data' => $this->t('Source')],
      ['data' => $this->t('Destination')],
      ['data' => $this->t('HTTP Status')],
      ['data' => $this->t('Last Checked')],
    ];

    // Build the table rows.
    $rows = [];
    foreach ($results as $result) {
      $rows[] = [
        'data' => [
          $result->source,
          $result->destination,
          $result->status,
          \Drupal::service('date.formatter')->format($result->checked, 'custom', 'd M Y H:i'),
        ],
      ];
    }

    // Build the table render array.
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No redirects returned a non-200 or non-300 response code.'),
    ];

    // Add the pager.
    $table['pager'] = [
      '#type' => 'pager',
    ];

    // Build the filter form.
    $filter_form = [
      '#type' => 'container',
      '#attributes' => ['class' => ['redirect-check-filters']],
      'source' => [
        '#type' => 'textfield',
        '#title' => $this->t('Source'),
        '#default_value' => $source_filter,
        '#attributes' => ['placeholder' => $this->t('Source')],
      ],
      'destination' => [
        '#type' => 'textfield',
        '#title' => $this->t('Destination'),
        '#default_value' => $destination_filter,
        '#attributes' => ['placeholder' => $this->t('Destination')],
      ],
      'actions' => [
        '#type' => 'actions',
        'filter' => [
          '#type' => 'submit',
          '#value' => $this->t('Filter'),
          '#submit' => [[$this, 'filterResults']],
        ],
      ],
    ];

    return [
      $filter_form,
      $form,
      $table,
    ];
  }

  /**
   * Filter results form submission handler.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function filterResults(array &$form, FormStateInterface $form_state) {
    $query_parameters = [];
    if ($source = $form_state->getValue('source')) {
      $query_parameters['source'] = $source;
    }
    if ($destination = $form_state->getValue('destination')) {
      $query_parameters['destination'] = $destination;
    }
    $form_state->setRedirect('dept_redirects.check_redirects', [], ['query' => $query_parameters]);
  }
}
