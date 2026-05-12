<?php

namespace Drupal\dept_fs\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for the Media Consolidator bulk action.
 */
class MediaConsolidatorConfirmForm extends ConfirmFormBase {

  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
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
    $entities = $this->entityTypeManager->getStorage('media')->loadMultiple($mids);

    $form['#title'] = $this->t('Consolidate Media');
    $form['mids'] = [
      '#type' => 'hidden',
      '#value' => implode(',', $mids),
    ];

    $options = [];
    foreach ($entities as $media) {

      $options[$media->id()] = $this->t(
        '@name (@bundle, ID: @id)',
        [
          '@name' => $media->label(),
          '@bundle' => $media->bundle(),
          '@id' => $media->id(),
        ]
      );
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
        function (array &$form, FormStateInterface $form_state): void {
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
    $database = \Drupal::database();
    $original_mid = $form_state->getValue('media_original');
    $mids = explode(',', $form_state->getValue('mids'));
    $mids = array_diff($mids, [$original_mid]);
    $entities = $media_storage->loadMultiple($mids);
    $entity_usage = \Drupal::service('entity_usage.usage');
    $sources = [];

    foreach ($entities as $entity) {
      $usage = $entity_usage->listSources($entity);

      foreach ($usage as $entity_type => $ids) {
        if (!array_key_exists($entity_type, $sources)) {
          $sources[$entity_type] = [];
        }

        foreach ($ids as $id => $items) {
          foreach ($items as $item) {
            $field_name = $item['field_name'];
            if (!array_key_exists($field_name, $sources[$entity_type])) {
              $sources[$entity_type][$field_name] = [];
            }

            if (!in_array($entity->id(), $sources[$entity_type][$field_name])) {
              array_push($sources[$entity_type][$field_name], $entity->id());
            }

          }
        }
      }

      foreach($sources as $entity_type => $fields) {
        foreach ($fields as $field => $data) {
          $table = $entity_type . "__" . $field;
          $target_column = $field . "_target_id";

          $database->update($table)
            ->fields([$target_column => $original_mid])
            ->condition($target_column, $mids, 'IN')
            ->execute();

          // Update revisions
          $table = $entity_type . "_revision__" . $field;

          $database->update($table)
            ->fields([$target_column => $original_mid])
            ->condition($target_column, $mids, 'IN')
            ->execute();

        }
      }



      // TODO: update target for usage place
      // TODO: cleaup entity usage records by calling bulkDeleteTargets()

    }

    ksm($sources);
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
