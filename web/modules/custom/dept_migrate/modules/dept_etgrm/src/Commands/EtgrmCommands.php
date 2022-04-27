<?php

namespace Drupal\dept_etgrm\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dept_etgrm\EtgrmBatchService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for interacting with ETGRM.
 */
class EtgrmCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Remove all relationships.
   *
   * @command etgrm:removeAll
   * @aliases etgrm:ra
   */
  public function removeAllCommand() {
    $dbConn = Database::getConnection('default', 'default');
    $dbConn->truncate('group_content')->execute();
    $dbConn->truncate('group_content_field_data')->execute();
    $this->io()->success('Removed all relationships');
  }

  /**
   * Create relationships by bundle id.
   *
   * @command etgrm:createByBundle
   * @aliases etgrm:cb
   */
  public function createCommand($bundle = '') {
    if (empty($bundle)) {
      $this->io->error('Please provide a bundle id to process');
    }

    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Creating group relationships for @bundle nodes', [
        '@bundle' => $bundle
      ]))
      ->addOperation([EtgrmBatchService::class, 'createNodeData'], [
        ['bundle' => $bundle, 'limit' => 100]
      ])
      ->addOperation([EtgrmBatchService::class, 'createNodeRelationships'], [
        ['bundle' => $bundle, 'limit' => 100]
      ])
      ->setFinishCallback([EtgrmBatchService::class, 'finishProcess']);

    batch_set($batch_builder->toArray());
    drush_backend_batch_process();

    $this->io()->success('Created relationships for ' . $bundle);
  }

  /**
   * Create all relationships.
   *
   * @command etgrm:createAll
   * @aliases etgrm:ca
   */
  public function all() {
    $schema = Database::getConnectionInfo('default')['default']['database'];
    $dbConn = Database::getConnection('default', 'default');

    $this->io()->title("Creating group content for migrated nodes.");

    $this->io()->write("Building node to group relationships");
    $dbConn->query("call CREATE_GROUP_RELATIONSHIPS('$schema')")->execute();
    $this->io()->writeln(" ✅");

    $this->io()->write("Expanding zero based domains to all groups");
    $dbConn->query("call PROCESS_GROUP_ZERO_RELATIONSHIPS()")->execute();
    $this->io()->writeln(" ✅");

    $this->io()->write("Creating Group Content data (this may take a while)");
    $dbConn->query("call PROCESS_GROUP_RELATIONSHIPS()")->execute();
    $this->io()->writeln(" ✅");

    $this->io()->success("Finished.");
  }

}
