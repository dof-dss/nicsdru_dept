<?php

namespace Drupal\dept_migrate_audit\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AuditFileUploadForm extends FormBase implements FormInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new AuditFileUploadForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(Connection $database, FileSystemInterface $file_system, DateFormatterInterface $date_formatter) {
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * @inheritDoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_system'),
      $container->get('date.formatter')
    );
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'audit_file_upload_form';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV file'),
      '#description' => $this->t('Upload a CSV file containing UUIDs.'),
      '#upload_location' => 'public://migrate_audit_csv_files',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
      '#submit' => ['::submitForm'],
    ];

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = $form_state->getValue('csv_file', 0);

    if (!empty($file)) {
      $csvfile = File::load($file[0]);
      $contents = file($csvfile->getFileUri(), FILE_IGNORE_NEW_LINES);
      // De-dupe just in case.
      $contents = array_unique($contents);

      $this->saveUuids($contents);
    }

    $form_state->setRedirect('dept_migrate_audit.results');
  }

  /**
   * Function to save UUID data to the database.
   *
   * @param array $uuids
   *   An array of UUIDS to insert.
   */
  protected function saveUuids(array $uuids) {
    $this->database->truncate('dept_migrate_audit')->execute();
    $now = \Drupal::time()->getRequestTime();

    foreach ($uuids as $uuid) {
      $uuid = str_replace('"', '', $uuid);

      $this->database->insert('dept_migrate_audit')
        ->fields([
          'uuid' => $uuid,
          'last_import' => $now,
        ])
        ->execute();
    }
  }

}
