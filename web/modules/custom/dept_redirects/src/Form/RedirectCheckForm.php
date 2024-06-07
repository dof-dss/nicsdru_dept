<?php

namespace Drupal\dept_redirects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\redirect\Entity\Redirect;
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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UrlGeneratorInterface $url_generator) {
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
    $destination_path = substr($destination_path, 1);

    // Get the base URL.
    $base_url = $this->urlGenerator->generateFromRoute('<front>', [], ['absolute' => TRUE]);

    if (preg_match('/^http/', $destination_path)) {
      // External link, keep it.
      $destination = $destination_path;
    }
    else {
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
          'source' => $redirect->getSourceUrl(),
          'destination' => $destination,
          'status' => $status_code,
          'checked' => $current_time,
        ];
      }
    } catch (RequestException $e) {
      $context['results'][] = [
        'source' => $redirect->getSourceUrl(),
        'destination' => $destination,
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
