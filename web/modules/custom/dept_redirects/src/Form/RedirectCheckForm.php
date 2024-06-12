<?php

namespace Drupal\dept_redirects\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RedirectCheckForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The URL generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Drupal\Core\Pager\PagerManagerInterface definition.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Drupal\Core\Pager\PagerParametersInterface definition.
   *
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  protected $pagerParameters;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConn;

  /**
   * Date formatter service object.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new RedirectCheckForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   * @param \Drupal\Core\Pager\PagerParametersInterface $pager_params
   *   The pager parameters service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UrlGeneratorInterface $url_generator, PagerManagerInterface $pager_manager, PagerParametersInterface $pager_params, Connection $connection, DateFormatterInterface $date_formatter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->urlGenerator = $url_generator;
    $this->pagerManager = $pager_manager;
    $this->pagerParameters = $pager_params;
    $this->dbConn = $connection;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('url_generator'),
      $container->get('pager.manager'),
      $container->get('pager.parameters'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dept_redirects_check_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dept_redirects.settings');
    $batch_size = $config->get('batch_size') ?? 50;

    // Get the total number of redirects and the number of processed redirects.
    $total_redirects = $this->entityTypeManager->getStorage('redirect')
      ->getQuery()
      ->count()
      ->accessCheck(TRUE)
      ->execute();
    $processed_redirects = $this->dbConn->select('dept_redirects_results', 'd')
      ->countQuery()
      ->distinct()
      ->execute()
      ->fetchField();

    // Display the number of redirects processed and the total so far.
    \Drupal::messenger()->addMessage($this->t('Flagged @processed out of @total redirects with a non-valid HTTP response code.', [
      '@processed' => $processed_redirects,
      '@total' => $total_redirects,
    ]));

    // Add the start batch process button.
    $form['batch_size'] = [
      '#type' => 'hidden',
      '#value' => $batch_size,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Redirect Check'),
      '#button_type' => 'primary',
    ];

    // Add the exposed filter form.
    $form['filter'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter Results'),
      'source' => [
        '#type' => 'textfield',
        '#title' => $this->t('Source URL'),
        '#default_value' => $form_state->getValue('source', ''),
      ],
      'destination' => [
        '#type' => 'textfield',
        '#title' => $this->t('Destination URL'),
        '#default_value' => $form_state->getValue('destination', ''),
      ],
      'response_text' => [
        '#type' => 'textfield',
        '#title' => $this->t('Response Text'),
        '#default_value' => $form_state->getValue('response_text', ''),
      ],
      'filter' => [
        '#type' => 'submit',
        '#value' => $this->t('Filter'),
        '#submit' => ['::filterResults'],
      ],
    ];

    // Add the results table with pager.
    $form['results'] = $this->buildResultsTable($form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_size = $form_state->getValue('batch_size') ?? 50;

    // Clear the results table.
    $this->clearResultsTable();

    // Initialize batch builder.
    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Checking redirects'))
      ->setInitMessage($this->t('Redirect check is starting...'))
      ->setProgressMessage($this->t('@current items out of @total.'))
      ->setErrorMessage($this->t('Redirect check has encountered an error.'))
      ->setFinishCallback([$this, 'finishBatch']);

    // Add the first batch operation to start processing redirects.
    $batch_builder->addOperation([$this, 'processRedirects'], [0, $batch_size]);

    // Set the batch.
    batch_set($batch_builder->toArray());

    // Redirect to batch processing page.
    $form_state->setRedirect('dept_redirects.check_redirects');
  }

  /**
   * Clears the results table.
   */
  protected function clearResultsTable() {
    $this->dbConn->truncate('dept_redirects_results')->execute();
  }

  /**
   * Process a chunk of redirects.
   *
   * @param int $offset
   *   The offset to start processing.
   * @param int $batch_size
   *   The number of redirects to process in each batch.
   * @param array $context
   *   The batch context array.
   */
  public function processRedirects($offset, $batch_size, array &$context) {
    // Initialize sandbox properties if not set.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
    }
    if (!isset($context['sandbox']['total'])) {
      $context['sandbox']['total'] = $this->entityTypeManager->getStorage('redirect')
        ->getQuery()
        ->count()
        ->accessCheck(TRUE)
        ->execute();
    }
    if (!isset($context['sandbox']['offset'])) {
      $context['sandbox']['offset'] = 0;
    }
    if (!isset($context['sandbox']['batch_size'])) {
      $context['sandbox']['batch_size'] = $batch_size;
    }

    $query = \Drupal::entityQuery('redirect')
      ->accessCheck(TRUE)
      ->sort('rid', 'ASC')
      ->range($context['sandbox']['offset'], $batch_size);

    $redirect_ids = $query->execute();

    if (empty($redirect_ids)) {
      // No more redirects to process.
      return;
    }

    /** @var \Drupal\redirect\Entity\Redirect[] $redirects */
    $redirects = Redirect::loadMultiple($redirect_ids);

    foreach ($redirects as $redirect) {
      $destination_path = $redirect->getRedirectUrl()->toString();

      // Determine if the destination is an absolute or external URL.
      if (str_starts_with($destination_path, 'www')) {
        $destination_path = 'https://' . $destination_path;
      }

      if (str_starts_with($destination_path, 'http')) {
        $destination = $destination_path;
      }
      else {
        // Get the base URL.
        $base_url = $this->urlGenerator->generateFromRoute('<front>', [], ['absolute' => TRUE]);
        // Construct the full URL.
        $destination = Url::fromUri($base_url . substr($destination_path, 1))->toString();
      }

      try {
        $response = \Drupal::httpClient()->head($destination, ['http_errors' => FALSE]);
        $status_code = $response->getStatusCode();
        $current_time = \Drupal::time()->getRequestTime();

        // Check if the status code is not in the 200 or 300 range.
        if ($status_code < 200 || $status_code >= 400) {
          $context['results'][] = [
            'rid' => $redirect->id(),
            'source' => $redirect->getSourceUrl(),
            'destination' => $destination_path,
            'status' => $status_code,
            'checked' => $current_time,
          ];
        }
      }
      catch (\Exception $e) {
        $context['results'][] = [
          'rid' => $redirect->id(),
          'source' => $redirect->getSourceUrl(),
          'destination' => $destination_path,
          'status' => 'Error: ' . $e->getMessage(),
          'checked' => \Drupal::time()->getRequestTime(),
        ];
      }

      // Store results in the database table.
      if (!empty($context['results'])) {
        foreach ($context['results'] as $result) {
          $this->dbConn->insert('dept_redirects_results')
            ->fields([
              'rid' => $result['rid'],
              'source' => $result['source'],
              'destination' => $result['destination'],
              'status' => $result['status'],
              'checked' => $result['checked'],
            ])
            ->execute();
        }
        // Clear results after inserting into the database.
        $context['results'] = [];
      }
    }

    // Update the sandbox progress and offset.
    $context['sandbox']['progress'] += count($redirect_ids);
    $context['sandbox']['offset'] += $batch_size;
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
    $context['message'] = $this->t('Processed @current items out of @total.', [
      '@current' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['total'],
    ]);

    // Schedule the next batch operation if there are more redirects to process.
    if ($context['sandbox']['offset'] < $context['sandbox']['total']) {
      $context['batch']['operations'][] = [
        [$this, 'processRedirects'],
        [$context['sandbox']['offset'], $context['sandbox']['batch_size']]
      ];
    }
  }

  /**
   * Build the results table.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The render array for the results table.
   */
  protected function buildResultsTable(FormStateInterface $form_state) {
    $header = [
      ['data' => $this->t('Redirect ID')],
      ['data' => $this->t('Source')],
      ['data' => $this->t('Destination')],
      ['data' => $this->t('HTTP Status')],
      ['data' => $this->t('Last Checked')],
      ['data' => $this->t('Operations')],
    ];

    $query = $this->dbConn->select('dept_redirects_results', 'd')
      ->fields('d', ['rid', 'source', 'destination', 'status', 'checked']);

    // Apply filters if provided.
    if ($source_filter = $form_state->getValue('source', '')) {
      $query->condition('d.source', '%' . $this->dbConn->escapeLike($source_filter) . '%', 'LIKE');
    }
    if ($destination_filter = $form_state->getValue('destination', '')) {
      $query->condition('d.destination', '%' . $this->dbConn->escapeLike($destination_filter) . '%', 'LIKE');
    }
    if ($response_text_filter = $form_state->getValue('response_text', '')) {
      $query->condition('d.status', '%' . $this->dbConn->escapeLike($response_text_filter) . '%', 'LIKE');
    }

    // Pager init.
    $page = $this->pagerParameters->findPage();
    $total_items = $query->countQuery()->execute()->fetchField() ?? 0;
    $num_per_page = 25;

    $results = $query
      // @phpstan-ignore-next-line
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      // @phpstan-ignore-next-line
      ->limit($num_per_page)
      ->execute();

    // Now that we have the total number of results, initialize the pager.
    $this->pagerManager->createPager($total_items, $num_per_page);

    // Get the current path including query parameters.
    $current_path = \Drupal::request()->getRequestUri();

    $rows = [];
    foreach ($results as $result) {
      $rows[] = [
        'data' => [
          $result->rid,
          $result->source,
          $result->destination,
          $result->status,
          $this->dateFormatter->format($result->checked, 'custom', 'd M Y H:i'),
          Link::createFromRoute($this->t('Edit'),
            'entity.redirect.edit_form',
            ['redirect' => $result->rid, 'destination' => $current_path]
          ),
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No redirects found.'),
      '#prefix' => '<div id="redirect-results-table">',
      '#suffix' => '</div>',
    ];

    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Filter results submission handler.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function filterResults(array &$form, FormStateInterface $form_state) {
    // Rebuild the form to apply filters.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Finish callback for the batch.
   *
   * @param bool $success
   *   Whether the batch process was successful.
   * @param array $results
   *   The results of the batch process.
   * @param array $operations
   *   Any remaining operations.
   */
  public function finishBatch($success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addStatus($this->t('Redirect check completed successfully.'));
    }
    else {
      \Drupal::messenger()->addError($this->t('Redirect check encountered an error.'));
    }
  }

}
