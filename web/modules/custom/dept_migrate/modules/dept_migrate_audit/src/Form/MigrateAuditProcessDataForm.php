<?php

declare(strict_types=1);

namespace Drupal\dept_migrate_audit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\dept_migrate_audit\MigrationAuditBatchService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Department sites: migration audit form.
 */
final class MigrateAuditProcessDataForm extends FormBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The batch service.
   *
   * @var \Drupal\dept_migrate_audit\MigrationAuditBatchService
   */
  protected $auditBatchService;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\dept_migrate_audit\MigrationAuditBatchService $batch
   *   THe batch service.
   * @param \Drupal\Core\Database\Connection $database
   * The database service.
   */
  public function __construct(MessengerInterface $messenger, MigrationAuditBatchService $audit_batch, Connection $database) {
    $this->messenger = $messenger;
    $this->auditBatchService = $audit_batch;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('dept_migrate_audit.audit_batch_service'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dept_migrate_audit_migrate_audit_process_data';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $results = $this->database->select('dept_migrate_audit', 'dma')
      ->fields('dma', ['uuid'])
      ->execute()->fetchAll();
    
    if (empty($results)) {
      $form['notice'] = [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => $this->t("Migrate Audit table is empty."),
      ];
    }
    else {
      $form['notice'] = [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => $this->t("Migrate Audit table contains data. @link", ['@link' => Link::createFromRoute('View audit results.', 'dept_migrate_audit.results')->toString()]),
      ];
    }

    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t("Click 'Start' to generate the Migrate Audit table."),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Start'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->auditBatchService->setupBatch();
  }

}
