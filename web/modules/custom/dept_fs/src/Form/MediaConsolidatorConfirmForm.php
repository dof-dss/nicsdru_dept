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
use Drupal\Core\Url;
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

    // Show why the selection cannot be consolidated before the user submits.
    $errors = $this->getSelectionErrors($entities);
    if (!empty($errors)) {
      foreach ($errors as $error) {
        $this->messenger()->addError($error);
      }
      $form['media_replacement']['#access'] = FALSE;
      $form['actions']['submit']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $mids = array_filter(explode(',', (string) $form_state->getValue('mids')));
    $entities = $this->entityTypeManager->getStorage('media')->loadMultiple($mids);

    foreach ($this->getSelectionErrors($entities) as $error) {
      $form_state->setErrorByName('media_replacement', $error);
    }
  }

  /**
   * Check the selected media can be safely consolidated.
   *
   * Media can only be consolidated when there are at least two items, all of
   * the same media type, with identical non-empty file checksums.
   *
   * @param \Drupal\media\MediaInterface[] $entities
   *   The selected media entities.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   A list of error messages, empty if the selection is valid.
   */
  protected function getSelectionErrors(array $entities): array {
    if (count($entities) < 2) {
      return [$this->t('Select at least two media items to consolidate.')];
    }

    $errors = [];
    $bundles = [];
    $checksums = [];

    foreach ($entities as $media) {
      $bundles[$media->bundle()] = TRUE;
      $checksum = $media->get('duplicates_checksum')->value;

      if (empty($checksum)) {
        $errors[] = $this->t('%name (ID: @id) has no file checksum, so it cannot be confirmed as a duplicate.', [
          '%name' => $media->label(),
          '@id' => $media->id(),
        ]);
      }
      else {
        $checksums[$checksum] = TRUE;
      }
    }

    if (count($bundles) > 1) {
      $errors[] = $this->t('The selected media items must all be the same media type.');
    }

    if (count($checksums) > 1) {
      $errors[] = $this->t('The selected media items are not duplicates of the same file.');
    }

    return $errors;
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
    $hosts = [];
    $skipped = [];

    // Run all updates in a transaction so a failure part way through does not
    // leave hosts referencing a mix of old and new media.
    $transaction = $this->database->startTransaction();

    try {
      foreach ($selected_media_entities as $media_entity) {
        $host_sources = $this->entityUsage->listSources($media_entity);

        foreach ($host_sources as $host_type => $host_data) {
          foreach ($host_data as $host_id => $usage) {
            $media_host = $this->entityTypeManager->getStorage($host_type)->load($host_id);
            $hosts[$host_type][$host_id] = $media_host;
            foreach ($usage as $usage_data) {
              $consolidation = new ConsolidationStore($media_host, $usage_data, $media_entity, $replacement_media);
              if (!$this->updateUsage($consolidation)) {
                $skipped[] = $this->t('@media used by @type @id (@method on @field)', [
                  '@media' => $media_entity->label(),
                  '@type' => $host_type,
                  '@id' => $host_id,
                  '@method' => $consolidation->relationshipType(),
                  '@field' => $consolidation->field(),
                ]);
              }
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->logger('dept_fs')->error('Media consolidation failed and was rolled back: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('The media could not be consolidated. No changes have been made.'));
      return;
    }

    // Commit the transaction before invalidating caches.
    unset($transaction);

    $this->invalidateCaches($hosts, [...$selected_media_entities, $replacement_media]);

    if (!empty($skipped)) {
      $this->messenger()->addWarning($this->t('The media has been partially consolidated. The following usages could not be updated automatically and still reference the duplicate media: @skipped', [
        '@skipped' => implode('; ', $skipped),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('The media has been consolidated.'));
    }
  }

  /**
   * Clear caches for the host entities and media affected by consolidation.
   *
   * Field values are written directly to the database, bypassing entity saves,
   * so the entity caches and cache tags a save would clear must be cleared
   * here.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[][] $hosts
   *   Host entities keyed by entity type ID and entity ID.
   * @param \Drupal\media\MediaInterface[] $media
   *   The duplicate and replacement media entities.
   */
  protected function invalidateCaches(array $hosts, array $media): void {
    $tags = [];

    foreach ($media as $media_entity) {
      $tags = array_merge($tags, $media_entity->getCacheTagsToInvalidate());
    }

    foreach ($hosts as $host_type => $entities) {
      // Reset the static and persistent entity caches so the host entities are
      // reloaded with the updated field values, e.g. on edit forms.
      $this->entityTypeManager->getStorage($host_type)->resetCache(array_keys($entities));

      foreach ($entities as $host) {
        // Invalidate the same tags as an entity save, covering rendered output
        // and listings (e.g. Views) that include the host entity.
        $tags = array_merge(
          $tags,
          $host->getCacheTagsToInvalidate(),
          $host->getEntityType()->getListCacheTags(),
          [$host_type . '_list:' . $host->bundle()],
        );
      }
    }

    Cache::invalidateTags(array_values(array_unique($tags)));
  }

  /**
   * Process a media entity by marshalling to the appropriate processor.
   *
   * @param \Drupal\dept_fs\ConsolidationStore $consolidation
   *   The store to process.
   *
   * @return bool
   *   TRUE if the usage was updated, FALSE if the relationship type is not
   *   supported and the usage was left unchanged.
   */
  protected function updateUsage(ConsolidationStore $consolidation): bool {
    switch ($consolidation->relationshipType()) {
      case 'entity_reference':
        $this->processEntityReference($consolidation);
        return TRUE;

      case 'media_embed':
        $this->processMediaEmbed($consolidation);
        return TRUE;

      default:
        return FALSE;
    }
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
    // Only match embeds of the media being consolidated.
    $media_regex = '/(<drupal-media\b[^>]*?)data-entity-uuid=["\']' . preg_quote($consolidation->currentMedia->uuid(), '/') . '["\']/i';
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
    if ($this->database->schema()->tableExists($consolidation->table(ConsolidationTable::Revision))) {
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
    }

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

    // Remove only this usage record for the duplicate, so any usages that were
    // not processed remain tracked against it.
    $this->entityUsage->registerUsage(
      $consolidation->currentMedia->id(),
      'media',
      $consolidation->mediaHost->id(),
      $consolidation->mediaHost->getEntityTypeId(),
      $consolidation->usageData['source_langcode'],
      $consolidation->usageData['source_vid'],
      $consolidation->relationshipType(),
      $consolidation->field(),
      0);
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
    return $this->t('Select the media to use in place of the duplicates.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('view.media_duplicates.media_duplicates_page');
  }

}
