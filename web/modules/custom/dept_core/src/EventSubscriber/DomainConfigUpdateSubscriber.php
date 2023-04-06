<?php

namespace Drupal\dept_core\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Departmental sites: core event subscriber.
 */
class DomainConfigUpdateSubscriber implements EventSubscriberInterface {

  /**
   * Config import event handler.
   *
   * @param \Drupal\Core\Config\ConfigImporterEvent $event
   *   Config import event.
   */
  public function onConfigImport(ConfigImporterEvent $event) {
    $change_list = $event->getChangelist('update');

    $changes = $event->getConfigImporter()->getProcessedConfiguration();

    // Check the list of updates for changes to Domain record config.
    if (!empty($changes['update'])) {
      $domain_updates = array_filter($changes['update'], function ($value, $key) {
        return str_starts_with($value, 'domain.record.');
      }, ARRAY_FILTER_USE_BOTH);

    }

    // Update the Department label with the latest Domain label. We don't allow
    // editing of the Department id or label to keep things synced.
    foreach ($domain_updates as $update) {
      $id = \Drupal::config($update)->get('id');
      $name = \Drupal::config($update)->get('name');

      $dept = \Drupal::entityTypeManager()->getStorage('department')->load($id);
      $dept->set('label', $name);
      $dept->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConfigEvents::IMPORT => ['onConfigImport'],
    ];
  }

}
