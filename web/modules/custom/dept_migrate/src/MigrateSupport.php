<?php

namespace Drupal\dept_migrate;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to support migrations.
 */
class MigrateSupport {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $d7conn;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Logger\LoggerChannel definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * Constructs a new instance of this object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactory $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('dept_migrate');
    $this->dbconn = Database::getConnection('default', 'default');
    $this->d7conn = Database::getConnection('default', 'migrate');
  }

  /**
   * TIdy up
   *
   * @param array $value
   *   The source value from the migration.
   *
   * @return array
   *   The value returned to the migrate pipeline.
   */
  public function prefixForExternalMigrationUrls(array $value) {
    if (!empty($value['url'])) {
      if (!preg_match('|^http?s:\/\/|', $value['url'])) {
        // Add the protocol in case it's been missed; force HTTPS.
        $value['url'] = 'https://' . $value['url'];
      }

      // If there's an email address (weird historical data)
      // log it and strip it out as we can't use it.
      if (preg_match('/.+@.+/', $value['url'])) {
        $this->logger->error($value['url'] . ' was supplied for a URL field and cannot be used');
        $value['url'] = '';
      }
    }

    // Subtle difference in attribute from D7 to D9.
    $value['uri'] = $value['url'];

    return $value;
  }

}
