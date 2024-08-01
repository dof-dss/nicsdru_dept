<?php

declare(strict_types=1);

namespace Drupal\dept_migrate_audit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
   * Constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\dept_migrate_audit\MigrationAuditBatchService $batch
   *   THe batch service.
   */
  public function __construct(MessengerInterface $messenger, MigrationAuditBatchService $audit_batch)
  {
    $this->messenger = $messenger;
    $this->auditBatchService = $audit_batch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('messenger'),
      $container->get('dept_migrate_audit.audit_batch_service'),
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

    $form['info'] = [
      '#markup' => $this->t("Click 'Start' to generate the Migrate Audit table.")
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
