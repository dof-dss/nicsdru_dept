<?php

namespace Drupal\dept_redirects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\redirect\Entity\Redirect;
use Drupal\Core\Database\Database;
use Drupal\Core\Batch\BatchBuilder;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;

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
   * Constructs a new RedirectCheckForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, $url_generator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('url_generator')
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
    $processed_redirects = Database::getConnection()->select('dept_redirects_results', 'd')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Display the number of redirects processed and the total so far.
    $form['status'] = [
      '#markup' => $this->t('Processed @processed out of @total redirects.', [
        '@processed' => $processed_redirects,
        '@total' => $total_redirects,
      ]),
    ];

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
    $batch_size = $form_state->getValue('batch_size');

    // Initialize batch builder.
    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Checking redirects'))
      ->setInitMessage($this->t('Redirect check is starting...'))
      ->setProgressMessage($this->t('Processed @current out of @total.'))
      ->setErrorMessage($this->t('Redirect check has encountered an error.'))
      ->setFinishCallback([$this, 'finishBatch']);

    // Load redirects to process.
    $redirect_ids = $this->loadRedirectsToProcess($batch_size);

    if (empty($redirect_ids)) {
      \Drupal::messenger()->addStatus($this->t('No redirects to process.'));
      return;
    }

    // Create batch operations for each redirect.
    foreach ($redirect_ids as $redirect_id) {
      $batch_builder->addOperation([$this, 'processRedirect'], [$redirect_id]);
    }

    // Set the batch.
    batch_set($batch_builder->toArray());

    // Redirect to batch processing page.
    $form_state->setRedirect('dept_redirects.check_redirects');
  }

  /**
   * Load redirects to process based on the batch size and last processed ID.
   *
   * @param int $batch_size
   *   The batch size.
   *
   * @return array
   *   An array of redirect IDs to process.
   */
  protected function loadRedirectsToProcess($batch_size) {
    // Get the last processed redirect ID from state.
    $last_processed_id = \Drupal::state()->get('dept_redirects_last_processed_id', 0);

    // Query to load the next batch of redirects.
    $query = \Drupal::entityQuery('redirect')
      ->condition('rid', $last_processed_id, '>')
      ->sort('rid', 'ASC')
      ->accessCheck(TRUE)
      ->range(0, $batch_size);

    return $query->execute();
  }

  /**
   * Process a single redirect.
   *
   * @param int $redirect_id
   *   The ID of the redirect entity.
   * @param array $context
   *   The batch context array.
   */
  public function processRedirect($redirect_id, array &$context) {
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = Redirect::load($redirect_id);
    $destination_path = $redirect->getRedirectUrl()->toString();

    // Determine if the destination is an absolute or external URL.
    if (str_starts_with($destination_path, 'http')) {
      $destination = $destination_path;
    }
    else {
      // Get the base URL.
      $base_url = $this->urlGenerator->generateFromRoute('<front>', [], ['absolute' => TRUE]);
      // Construct the full URL.
      $destination = Url::fromUri($base_url . $destination_path)->toString();
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
    } catch (RequestException $e) {
      $context['results'][] = [
        'rid' => $redirect->id(),
        'source' => $redirect->getSourceUrl(),
        'destination' => $destination_path,
        'status' => 'Error: ' . $e->getMessage(),
        'checked' => \Drupal::time()->getRequestTime(),
      ];
    }

    // Store results in the custom database table.
    if (!empty($context['results'])) {
      $connection = \Drupal::database();
      foreach ($context['results'] as $result) {
        $connection->insert('dept_redirects_results')
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

    // Update the last processed ID in state.
    \Drupal::state()->set('dept_redirects_last_processed_id', $redirect_id);
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

    $query = Database::getConnection()->select('dept_redirects_results', 'd')
      ->fields('d', ['rid', 'source', 'destination', 'status', 'checked']);

    // Apply filters if provided.
    if ($source_filter = $form_state->getValue('source', '')) {
      $query->condition('d.source', '%' . Database::getConnection()->escapeLike($source_filter) . '%', 'LIKE');
    }
    if ($destination_filter = $form_state->getValue('destination', '')) {
      $query->condition('d.destination', '%' . Database::getConnection()->escapeLike($destination_filter) . '%', 'LIKE');
    }

    $results = $query
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(25)
      ->execute();

    $rows = [];
    foreach ($results as $result) {
      $edit_link = Url::fromRoute('entity.redirect.edit_form', ['redirect' => $result->rid])->toString();
      $rows[] = [
        'data' => [
          $result->rid,
          $result->source,
          $result->destination,
          $result->status,
          \Drupal::service('date.formatter')->format($result->checked, 'custom', 'd M Y H:i'),
          Link::createFromRoute($this->t('Edit'), 'entity.redirect.edit_form', ['redirect' => $result->rid]),
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No redirects found.'),
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      'pager' => ['#type' => 'pager'],
    ];
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
