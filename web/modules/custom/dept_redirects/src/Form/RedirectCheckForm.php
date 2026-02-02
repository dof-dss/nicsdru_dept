<?php

namespace Drupal\dept_redirects\Form;

use Drupal\Component\Datetime\TimeInterface;
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
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class RedirectCheckForm extends FormBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected UrlGeneratorInterface $urlGenerator;
  protected PagerManagerInterface $pagerManager;
  protected PagerParametersInterface $pagerParameters;
  protected Connection $dbConn;
  protected DateFormatterInterface $dateFormatter;

  protected ClientInterface $httpClient;
  protected TimeInterface $time;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    UrlGeneratorInterface $url_generator,
    PagerManagerInterface $pager_manager,
    PagerParametersInterface $pager_params,
    Connection $connection,
    DateFormatterInterface $date_formatter,
    ClientInterface $http_client,
    TimeInterface $time,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->urlGenerator = $url_generator;
    $this->pagerManager = $pager_manager;
    $this->pagerParameters = $pager_params;
    $this->dbConn = $connection;
    $this->dateFormatter = $date_formatter;

    $this->httpClient = $http_client;
    $this->time = $time;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('url_generator'),
      $container->get('pager.manager'),
      $container->get('pager.parameters'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('http_client'),
      $container->get('datetime.time'),
    );
  }

  public function getFormId(): string {
    return 'dept_redirects_check_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('dept_redirects.settings');
    $batch_size = $config->get('batch_size') ?? 50;

    $total_redirects = $this->entityTypeManager->getStorage('redirect')
      ->getQuery()
      ->count()
      ->accessCheck(TRUE)
      ->execute();

    $processed_redirects = (int) $this->dbConn->select('dept_redirects_results', 'd')
      ->countQuery()
      ->distinct()
      ->execute()
      ->fetchField();

    // Use FormBase messenger() (no static call, no property override).
    $this->messenger()->addMessage($this->t(
      'Flagged @processed out of @total redirects with a non-valid HTTP response code.',
      ['@processed' => $processed_redirects, '@total' => $total_redirects]
    ));

    $form['batch_size'] = [
      '#type' => 'hidden',
      '#value' => $batch_size,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Redirect Check'),
      '#button_type' => 'primary',
    ];

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

    $form['results'] = $this->buildResultsTable($form_state);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $batch_size = (int) ($form_state->getValue('batch_size') ?? 50);

    $this->clearResultsTable();

    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Checking redirects'))
      ->setInitMessage($this->t('Redirect check is starting...'))
      ->setProgressMessage($this->t('@current items out of @total.'))
      ->setErrorMessage($this->t('Redirect check has encountered an error.'))
      ->setFinishCallback([$this, 'finishBatch']);

    $batch_builder->addOperation([$this, 'processRedirects'], [0, $batch_size]);

    batch_set($batch_builder->toArray());
    $form_state->setRedirect('dept_redirects.check_redirects');
  }

  protected function clearResultsTable(): void {
    $this->dbConn->truncate('dept_redirects_results')->execute();
  }

  public function processRedirects($offset, $batch_size, array &$context): void {
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
      $context['sandbox']['batch_size'] = (int) $batch_size;
    }

    $redirect_ids = $this->entityTypeManager->getStorage('redirect')
      ->getQuery()
      ->accessCheck(TRUE)
      ->sort('rid', 'ASC')
      ->range($context['sandbox']['offset'], (int) $batch_size)
      ->execute();

    if (empty($redirect_ids)) {
      return;
    }

    $redirects = $this->entityTypeManager->getStorage('redirect')->loadMultiple($redirect_ids);

    foreach ($redirects as $redirect) {
      /** @var \Drupal\redirect\Entity\Redirect $redirect */
      $destination_path = $redirect->getRedirectUrl()->toString();

      if (str_starts_with($destination_path, 'www')) {
        $destination_path = 'https://' . $destination_path;
      }

      if (str_starts_with($destination_path, 'http')) {
        $destination = $destination_path;
      }
      else {
        $base_url = $this->urlGenerator->generateFromRoute('<front>', [], ['absolute' => TRUE]);
        $destination = Url::fromUri($base_url . substr($destination_path, 1))->toString();
      }

      $checked = $this->time->getRequestTime();

      try {
        $response = $this->httpClient->head($destination, ['http_errors' => FALSE]);
        $status_code = $response->getStatusCode();

        if ($status_code < 200 || $status_code >= 400) {
          $this->dbConn->insert('dept_redirects_results')
            ->fields([
              'rid' => $redirect->id(),
              'source' => $redirect->getSourceUrl(),
              'destination' => $destination_path,
              'status' => $status_code,
              'checked' => $checked,
            ])
            ->execute();
        }
      }
      catch (\Throwable $e) {
        $this->dbConn->insert('dept_redirects_results')
          ->fields([
            'rid' => $redirect->id(),
            'source' => $redirect->getSourceUrl(),
            'destination' => $destination_path,
            'status' => 'Error: ' . $e->getMessage(),
            'checked' => $checked,
          ])
          ->execute();
      }
    }

    $context['sandbox']['progress'] += count($redirect_ids);
    $context['sandbox']['offset'] += (int) $batch_size;

    $total = (int) $context['sandbox']['total'];
    $progress = (int) $context['sandbox']['progress'];

    $context['finished'] = $total > 0 ? $progress / $total : 1;
    $context['message'] = $this->t('Processed @current items out of @total.', [
      '@current' => $progress,
      '@total' => $total,
    ]);

    if ($context['sandbox']['offset'] < $total) {
      $context['batch']['operations'][] = [
        [$this, 'processRedirects'],
        [$context['sandbox']['offset'], $context['sandbox']['batch_size']],
      ];
    }
  }

  protected function buildResultsTable(FormStateInterface $form_state): array {
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

    if ($source_filter = $form_state->getValue('source', '')) {
      $query->condition('d.source', '%' . $this->dbConn->escapeLike($source_filter) . '%', 'LIKE');
    }
    if ($destination_filter = $form_state->getValue('destination', '')) {
      $query->condition('d.destination', '%' . $this->dbConn->escapeLike($destination_filter) . '%', 'LIKE');
    }
    if ($response_text_filter = $form_state->getValue('response_text', '')) {
      $query->condition('d.status', '%' . $this->dbConn->escapeLike($response_text_filter) . '%', 'LIKE');
    }

    $total_items = (int) ($query->countQuery()->execute()->fetchField() ?? 0);
    $num_per_page = 25;

    $results = $query
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit($num_per_page)
      ->execute();

    $this->pagerManager->createPager($total_items, $num_per_page);

    // Replace \Drupal::request() with FormBase::getRequest().
    $current_path = $this->getRequest()->getRequestUri();

    $rows = [];
    foreach ($results as $result) {
      $rows[] = [
        'data' => [
          $result->rid,
          $result->source,
          $result->destination,
          $result->status,
          $this->dateFormatter->format((int) $result->checked, 'custom', 'd M Y H:i'),
          Link::createFromRoute(
            $this->t('Edit'),
            'entity.redirect.edit_form',
            ['redirect' => $result->rid, 'destination' => $current_path]
          ),
        ],
      ];
    }

    return [
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No redirects found.'),
        '#prefix' => '<div id="redirect-results-table">',
        '#suffix' => '</div>',
      ],
      'pager' => ['#type' => 'pager'],
    ];
  }

  public function filterResults(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild(TRUE);
  }

  public function finishBatch($success, array $results, array $operations): void {
    if ($success) {
      $this->messenger()->addStatus($this->t('Redirect check completed successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Redirect check encountered an error.'));
    }
  }

}
