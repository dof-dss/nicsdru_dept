<?php

namespace Drupal\dept_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\dept_migrate\MigrateUuidLookupManager;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateIdMapMessageEvent;
use Drupal\migrate\MigrateMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class MigrateMessageSubscriber.
 */
final class MigrateMessageSubscriber implements EventSubscriberInterface {

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\dept_migrate\MigrateUuidLookupManager $lookupManager
   *   Lookup Manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MigrateUuidLookupManager $lookupManager,
    protected LoggerChannelFactory $logger,
  ) {
  }

  /**
   * Callback to respond to migration idmap message event.
   *
   * We try to use this to expand on any vague messages to
   * give them more context and aid debugging.
   *
   * @param \Drupal\migrate\Event\MigrateIdMapMessageEvent $event
   *   The idmap message event object.
   */
  public function onLogMigrateMessage(MigrateIdMapMessageEvent $event) {
    $message = $event->getMessage();
    if (!preg_match('/Value is not a valid entity/i', $message)) {
      return;
    }

    $source = $event->getSourceIdValues();

    $uuid = $source['uuid'];
    $d7_lookup = $this->lookupManager->lookupBySourceUuId([$uuid]);
    $d7_node = $d7_lookup[$uuid];

    $better_message = t('Source item ":stitle" (:uuid) had a problem locating other referenced content. Review the migrate script output, or log file, for further details.', [
      ':uuid' => $uuid,
      ':stitle' => $d7_node['d7title'],
    ]);

    $event->getMigration()->getIdMap()->clearMessages();
    $event->getMigration()->getIdMap()->saveMessage($source, $better_message);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    $events = [];
    $events[MigrateEvents::IDMAP_MESSAGE] = ['onLogMigrateMessage'];
    return $events;
  }

}
