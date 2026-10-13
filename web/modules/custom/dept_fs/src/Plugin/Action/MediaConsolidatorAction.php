<?php

namespace Drupal\dept_fs\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Action to consolidate duplicate Media items.
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
    $entities = [];

    foreach ($objects as $entity) {
      $entities[$entity->id()] = $entity->get('duplicates_checksum')->value;
    }

    // Ensure the selected Media entities all duplicates of the same type.
    if (!$this->areMatchingDuplicates(array_values($entities))) {
      $this->messenger->addWarning($this->t('The selected Media items are not matching duplicates.'));
    }

    // Store in private temp store so the confirmation form can retrieve them.
    $this->tempStoreFactory
      ->get('media_consolidator')
      ->set('selected_media', array_keys($entities));

    // Get the current page URL as destination for the confirm form cancel button.
    $destination = \Drupal::destination()->get();
    $url = Url::fromRoute('dept_fs.media_consolidator_confirm', [], ['query' => ['destination' => $destination]]);
    $redirect = new RedirectResponse($url->toString());
    $redirect->send();
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

  /**
   * Determine if the supplied checksums match
   *
   * @param array $array
   *   List of checksums to check.
   *
   * @return bool
   *   True if all match, else false.
   */
  private function areMatchingDuplicates($array) {
    return count(array_unique($array)) === 1;
  }

}
