<?php

namespace Drupal\dept_fs\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action to consolidate duplicate media items.
 */
#[Action(
  id: 'media_consolidator_action',
  label: new TranslatableMarkup('Consolidate Media'),
  type: 'media'
)]
class MediaConsolidatorAction extends ActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tempstore.private'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $objects): void {
    // Collect IDs from the selected media entity objects.
    $ids = array_map(static fn($entity) => $entity->id(), $objects);

    // Store in private temp store so the confirmation form can retrieve them.
    $this->tempStoreFactory
      ->get('media_consolidator')
      ->set('selected_media', array_values($ids));
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL): void {
    if ($object !== NULL) {
      $this->executeMultiple([$object]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool {
    return in_array('administrator', $account->getRoles());
  }

}
