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
use Drupal\dept_fs\ConsolidationStore;
use Drupal\dept_fs\ConsolidationTable;
use Drupal\entity_usage\EntityUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for the Media Consolidator bulk action.
 */
class MediaConsolidatorConfirmForm extends ConfirmFormBase {

  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStore,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly Connection $database,
    protected readonly EntityUsageInterface $entityUsage,
  ) {

  }

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
    $store = $this->tempStore->get('media_consolidator');
    $mids = $store->get('selected_media') ?? [];

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

    $form['media_replacement'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select the media to use in place of the duplicates.'),
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
        function (array &$form, FormStateInterface $form_state): void {
          $this->submitForm($form, $form_state);
        },
      ],
    ];
    $form['actions']['cancel'] = ConfirmFormHelper::buildCancelLink($this, \Drupal::request());

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $media_storage = \Drupal::entityTypeManager()->getStorage('media');
    $replacement_media_mid = $form_state->getValue('media_replacement');
    $mids = explode(',', $form_state->getValue('mids'));
    // Remove the replacement mid from the list to be processed.
    $mids = array_diff($mids, [$replacement_media_mid]);
    $selected_media_entities = $media_storage->loadMultiple($mids);
    $replacement_media = $media_storage->load($replacement_media_mid);
    $cache_tags = [];

    foreach ($selected_media_entities as $media_entity) {
      $host_sources = $this->entityUsage->listSources($media_entity);

      foreach ($host_sources as $host_type => $host_data) {
        foreach ($host_data as $host_id => $usage) {
          $media_host = $this->entityTypeManager->getStorage($host_type)->load($host_id);
          foreach ($usage as $usage_index => $usage_data) {
            $this->updateUsage(new ConsolidationStore($media_host, $usage_data, $media_entity, $replacement_media));
          }
        }
      }

      $this->entityUsage->deleteByTargetEntity($media_entity->id(), 'media');
    }

    Cache::invalidateTags($cache_tags);
  }

  /**
   * Process a media entity by marshalling to the appropriate processor.
   *
   * @param \Drupal\dept_fs\ConsolidationStore $consolidation
   *   The store to process.
   */
  protected function updateUsage(ConsolidationStore $consolidation) {
    match($consolidation->relationshipType()) {
      'entity_reference' => $this->processEntityReference($consolidation),
      'media_embed' =>  $this->processMediaEmbed($consolidation),
    };
  }

  /**
   * Processes media that is linked via entity reference.
   *
   * @param \Drupal\dept_fs\ConsolidationStore $consolidation
   *   The store to process.
   */
  protected function processEntityReference(ConsolidationStore $consolidation) {
    // TODO: Provide target method on store, but must take into account the relationship method.
    $table_target_column = $consolidation->field() . "_target_id";

    // If the source vid matches the media host entity revision ID then update the base table.
    if ($consolidation->mediaHost->getLoadedRevisionId() == $consolidation->usageData['source_vid']) {
      $this->database->update($consolidation->table(ConsolidationTable::Base))
        ->fields([$table_target_column => $consolidation->replacementMedia->id()])
        ->condition($table_target_column, $consolidation->currentMedia->id())
        ->condition('revision_id', $consolidation->usageData['source_vid'])
        ->execute();
    }

    // Update target_id in usage revisions if entity is revisionable.
    if ($this->database->schema()->tableExists($consolidation->table(ConsolidationTable::Revision))) {
      $this->database->update($consolidation->table(ConsolidationTable::Revision))
        ->fields([$table_target_column => $consolidation->replacementMedia->id()])
        ->condition($table_target_column, $consolidation->currentMedia->id())
        ->condition('revision_id', $consolidation->usageData['source_vid'])
        ->execute();
    }

    $this->updateEntityUsage($consolidation);
  }

  /**
   * Processes media that has been embedded in a field.
   *
   * @param \Drupal\dept_fs\ConsolidationStore $consolidation
   *   The store to process.
   */
  protected function processMediaEmbed(ConsolidationStore $consolidation) {

    $field = $consolidation->field() . "_value";
    $media_regex = '/(<drupal-media\b[\s\S]*?)data-entity-uuid=["\'](?:[^"\']*)["\']/i';
    $updated_media_element = '${1}data-entity-uuid="' . $consolidation->replacementMedia->uuid() . '"';

    // If the source vid matches the media host entity revision ID then update the base table.
    if ($consolidation->mediaHost->getLoadedRevisionId() == $consolidation->usageData['source_vid']) {
      $field_value = $this->database->select($consolidation->table(ConsolidationTable::Base), 't')
        ->fields('t', [$field])
        ->condition('entity_id', $consolidation->mediaHost->id())
        ->condition('revision_id', $consolidation->usageData['source_vid'])
        ->condition('langcode', $consolidation->usageData['source_langcode'])
        ->execute()->fetchField();

      $field_value = preg_replace($media_regex, $updated_media_element, $field_value);

      $this->database->update($consolidation->table(ConsolidationTable::Base))
        ->fields([$field => $field_value])
        ->condition('entity_id', $consolidation->mediaHost->id())
        ->condition('revision_id', $consolidation->usageData['source_vid'])
        ->condition('langcode', $consolidation->usageData['source_langcode'])
        ->execute();
    }

    // Update media embed data in revisions.
    $field_value = $this->database->select($consolidation->table(ConsolidationTable::Revision), 't')
      ->fields('t', [$field])
      ->condition('entity_id', $consolidation->mediaHost->id())
      ->condition('revision_id', $consolidation->usageData['source_vid'])
      ->condition('langcode', $consolidation->usageData['source_langcode'])
      ->execute()
      ->fetchField();

    $field_value = preg_replace($media_regex, $updated_media_element, $field_value);

    $this->database->update($consolidation->table(ConsolidationTable::Revision))
      ->fields([$field => $field_value])
      ->condition('entity_id', $consolidation->mediaHost->id())
      ->condition('revision_id', $consolidation->usageData['source_vid'])
      ->condition('langcode', $consolidation->usageData['source_langcode'])
      ->execute();

    $this->updateEntityUsage($consolidation);
  }

  /**
   * Update the Entity Usage data.
   *
   * @param \Drupal\dept_fs\ConsolidationStore $consolidation
   *   The consolodation store to update.
   */
  public function updateEntityUsage(ConsolidationStore $consolidation): void {
    $this->entityUsage->registerUsage(
      $consolidation->replacementMedia->id(),
      'media',
      $consolidation->mediaHost->id(),
      $consolidation->mediaHost->getEntityTypeId(),
      $consolidation->usageData['source_langcode'],
      $consolidation->usageData['source_vid'],
      $consolidation->relationshipType(),
      $consolidation->field(),
      1);
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
