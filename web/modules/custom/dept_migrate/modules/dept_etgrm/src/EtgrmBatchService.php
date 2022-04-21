<?php

namespace Drupal\dept_etgrm;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\node\Entity\Node;


/**
 * Entity To Group Relationship Manager batch service.
 */
class EtgrmBatchService {

  /**
   * Maps Drupal 7 domain ID's to Drupal 9 group ID's.
   *
   * @param int $domain_id
   *  A domain id.
   * @return int
   *  Corresponding group id, 0 for retired site and -1 for not found.
   */
  public static function domainIDtoGroupId(int $domain_id) {
    $map = [
      1 => 1,   // nigov.site
      2 => 3,   // daera.site
      3 => 0,   // del.vm
      4 => 5,   // economy.site
      5 => 7,   // execoffice.site
      6 => 6,   // education.site
      7 => 2,   // finance.site
      8 => 8,   // health.site
      9 => 9,   // infrastructure.site
      10 => 0,  // dcal.vm
      11 => 0,  // doe.vm
      12 => 10, // justice.site
      13 => 4,  // communities.site
    ];

    if (array_key_exists($domain_id, $map)) {
      return $map[$domain_id];
    }

    return -1;
  }

  /**
   * Init operation task by retrieving all content to be updated.
   *
   * @param array $args
   * @param array $context
   */
  public static function createNodeData($args, &$context) {
    $bundle = $args['bundle'];
    $migration_table = 'migrate_map_node_' . $bundle;
    $limit = $args['limit'];
    $offset = (!empty($context['sandbox']['offset'])) ? $context['sandbox']['offset'] : 0;

    // Define total on first call.
    if (!isset($context['sandbox']['total'])) {
      $context['sandbox']['total'] = \Drupal::database()
        ->select($migration_table)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    $query = \Drupal::database()->select($migration_table, 'mt');
    $query->addField('mt', 'sourceid3', 'domains');
    $query->addField('mt', 'destid1', 'nid');
    $query->range($offset, $limit);
    $result = $query->execute();

    $results = $result->fetchAll();


    // Setup results based on retrieved objects.
    $context['results'] = array_reduce($results,
      function ($carry, $object) {
        // Map object results extracted from previous query.
        $carry[$object->nid] = array_map(fn($domain) => self::domainIDtoGroupId($domain[0]), explode('-', $object->domains));
        return $carry;
      }, $context['results']
    );

    // Redefine offset value.
    $context['sandbox']['offset'] = $offset + $limit;

    // Set current step as unfinished until offset is greater than total.
    $context['finished'] = 0;
    if ($context['sandbox']['offset'] >= $context['sandbox']['total']) {
      $context['finished'] = 1;
    }

    // Setup info message to notify about current progress.
    $context['message'] = t(
      'Processed @offset nodes of @total',
      [
        '@offset' => $context['sandbox']['offset'],
        '@total' => $context['sandbox']['total'],
      ]
    );
  }

  public static function createNodeRelationships($args, &$context) {

    if (!isset($context['sandbox']['total'])) {
      $context['sandbox']['total'] = count($context['results']);
    }

    $bundle = $args['bundle'];
    $limit = $args['limit'];

    // Walk-through all results in order to update them.
    $count = 0;
    foreach ($context['results'] as $nid => $groups) {

      $node = Node::load($nid);

      if (empty($node)) {
        continue;
      }

      foreach ($groups as $group) {
        if ($group === 0){
          continue;
        }
        $group = Group::load($group);
        $group->addContent($node, 'group_node:' . $bundle);
      }


      // Increment count at one.
      $count++;

      // Remove current result.
      unset($context['results'][$nid]);
      if ($count >= $limit) {
        break;
      }
    }

    // Setup message to notify how many remaining articles.
    $context['message'] = t(
      'Creating @bundle relationships, @total remaining.', [
        '@bundle' => $bundle,
        '@total' => count($context['results'])
      ]
    );

    // Set current step as unfinished until there's not results.
    $context['finished'] = (empty($context['results']));

    // When it is completed, then setup result as total amount updated.
    if ($context['finished']) {
      $context['results'] = $context['sandbox']['total'];
    }
  }

  public static function finishProcess($success, $results, $operations) {
    // Setup final message after process is done.
    $message = ($success) ?
      t('Update process of @count articles was completed.',
        ['@count' => $results]) :
      t('Finished with an error.');
    \Drupal::messenger()->addMessage($message);
  }

}
