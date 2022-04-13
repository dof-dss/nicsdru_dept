<?php

namespace Drupal\dept_etgrm\Commands;

use Drupal\dept_etgrm\EntityToGroupRelationshipManagerService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for interacting with ETGRM.
 */
class EtgrmCommands extends DrushCommands {

  /**
   * The ETGRM service.
   *
   * @var \Drupal\dept_etgrm\EntityToGroupRelationshipManagerService
   */
  protected $etgrmMananger;

  /**
   *
   */
  public function __construct(EntityToGroupRelationshipManagerService $etgrm_mananger) {
    parent::__construct();
    $this->etgrmMananger = $etgrm_mananger;
  }

  /**
   * Remove all relationships.
   *
   * @command etgrm:removeAll
   * @aliases etgrm:ra
   */
  public function removeAllCommand() {
    $this->etgrmMananger->remove()->all();
    $this->io()->success('Removed all relationships');
  }

  /**
   * Create all relationships.
   *
   * @command etgrm:createAll
   * @aliases etgrm:ca
   */
  public function createAllCommand() {
    $count = $this->etgrmMananger->create()->all();
    $this->io()->success('Created ' . $count . ' relationships');
  }

}
