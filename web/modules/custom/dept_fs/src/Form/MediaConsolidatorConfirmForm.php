<?php

namespace Drupal\dept_fs\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\entity_usage\EntityUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for the Media Consolidator bulk action.
 */
class MediaConsolidatorConfirmForm extends ConfirmFormBase {

  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly Connection $database,
    protected readonly EntityUsageInterface $entityUsage,) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('database'),
      $container->get('entity_usage.usage'),);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'media_consolidator_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $store = $this->tempStoreFactory->get('media_consolidator');
    $mids = $store->get('selected_media') ?? [];
    //    $store->delete('selected_media');

    /** @var \Drupal\media\MediaInterface[] $entities */
    $entities = $this->entityTypeManager->getStorage('media')
      ->loadMultiple($mids);

    $form['#title'] = $this->t('Consolidate Media');
    $form['mids'] = [
      '#type' => 'hidden',
      '#value' => implode(',', $mids),
    ];

    $options = [];
    foreach ($entities as $media) {
      $options[$media->id()] = $this->t('@name (@bundle, ID: @id)', [
          '@name' => $media->label(),
          '@bundle' => $media->bundle(),
          '@id' => $media->id(),
        ]);
    }

    $form['media_original'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select the media origin, this will replace all of the duplicates on this page.'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => key($options),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->getConfirmText(),
      '#button_type' => 'primary',
      '#submit' => [
        function(array &$form, FormStateInterface $form_state): void {
          $this->submitForm($form, $form_state);
        },
      ],
    ];
    $form['actions']['cancel'] = ConfirmFormHelper::buildCancelLink($this, \Drupal::request());

    // TODO: Process media items.
    // TODO: Clean the private temp store when submitted or cancelled.
    // TODO: Improve display of selected media.

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $media_storage = \Drupal::entityTypeManager()->getStorage('media');
    $original_mid = $form_state->getValue('media_original');
    $mids = explode(',', $form_state->getValue('mids'));
    $mids = array_diff($mids, [$original_mid]);
    $entities = $media_storage->loadMultiple($mids);
    $original_media = $media_storage->load($original_mid);
    $cache_tags = [];
    $sources_map = [];

    foreach ($entities as $media) {
      $content_sources = $this->entityUsage->listSources($media);

      foreach ($content_sources as $content_type => $content_data) {
        foreach ($content_data as $content_id => $usage_indexes) {
          foreach ($usage_indexes as $usage_index) {
            $this->updateUsage([
              'content_type' => $content_type,
              'content_id' => $content_id,
              'usage' => $usage_index,
              'media' => $media,
              'original_media' => $original_media,
            ]);
          }
        }
        // TODO: cleanup entity usage records by calling bulkDeleteTargets()

      }

      $this->entityUsage->deleteByTargetEntity($media->id(), 'media');
    }

    Cache::invalidateTags($cache_tags);
  }

  protected function updateUsage($update_data) {
    match($update_data['usage']['method']) {
      'entity_reference' => $this->processEntityReference($update_data),
      'media_embed' =>  $this->processMediaEmbed($update_data),
      'layout_builder' =>  $this->processLayoutBuilder($update_data),
      'block_field' =>  $this->processBlockField($update_data),
    };
  }

  protected function processEntityReference($update_data) {
    extract($update_data);
    $table = $content_type . "__" . $usage['field_name'];
    $target_column = $usage['field_name'] . "_target_id";

    $this->database->update($table)
      ->fields([$target_column => $original_media->id()])
      ->condition($target_column, $media->id())
      ->execute();

    $table = $content_type . "_revision__" . $usage['field_name'];

    if ($this->database->schema()->tableExists($table)) {
      $this->database->update($table)
        ->fields([$target_column => $original_media->id()])
        ->condition($target_column, $media->id())
        ->execute();
    }

    $this->entityUsage->registerUsage(
      $original_media->id(),
      'media',
      $content_id,
      $content_type,
      $usage['source_langcode'],
      $usage['source_vid'],
      $usage['method'],
      $usage['field_name'],
      1);
  }

  protected function processMediaEmbed($update_data) {

    extract($update_data);
    $table = $content_type . "__" . $usage['field_name'];
    $field = $usage['field_name'] . "_value";

    $field_value = $this->database->select($table, 't')
      ->fields('t', [$field])
      ->condition('entity_id', $content_id)
      ->condition('revision_id', $usage['source_vid'])
      ->condition('revision_id', $usage['source_vid'])
      ->condition('langcode', $usage['source_langcode'])
      ->execute()->fetchField();

    $media_regex = '/(<drupal-media\b[\s\S]*?)data-entity-uuid=["\'](?:[^"\']*)["\']/i';
    $updated_media_element = '${1}data-entity-uuid="' . $original_media->uuid() . '"';

    $field_value = preg_replace($media_regex, $updated_media_element, $field_value);

    $this->database->update($table)
      ->fields([$field => $field_value])
      ->condition('entity_id', $content_id)
      ->condition('revision_id', $usage['source_vid'])
      ->condition('revision_id', $usage['source_vid'])
      ->condition('langcode', $usage['source_langcode'])
      ->execute();

    $this->entityUsage->registerUsage(
      $original_media->id(),
      'media',
      $content_id,
      $content_type,
      $usage['source_langcode'],
      $usage['source_vid'],
      $usage['method'],
      $usage['field_name'],
      1);
  }

  protected function processLayoutBuilder($update_data) {
    ksm("processLayoutBuilder", $update_data);
  }

  protected function processBlockField($update_data) {
    ksm("processBlockField", $update_data);
  }




  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Consolidate');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {

  }



}
