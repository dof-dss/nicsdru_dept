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

    /** @var \Drupal\media\MediaInterface[] $entities */
    $entities = $this->entityTypeManager->getStorage('media')->loadMultiple($mids);

    $form['#title'] = $this->t('Consolidate Media');

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

    $form['media_origin'] = [
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
