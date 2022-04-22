<?php

namespace Drupal\dept_etgrm;

use Drupal\group\Entity\Group;
use Drupal\node\Entity\Node;

/**
 * Entity To Group Relationship Manager batch service.
 */
class EtgrmBatchService {

  /**
   * Maps Drupal 7 domain ID's to Drupal 9 group ID's.
   *
   * @param int $domain_id
   *   A domain id.
   * @return int
   *   Corresponding group id, 0 for all sites and -1 for retired/not found.
   */
  public static function domainIdtoGroupId(int $domain_id) {
    $map = [
      // All sites.
      0 => 0,
      // nigov.site.
      1 => 1,
      // daera.site.
      2 => 3,
      // del.vm.
      3 => -1,
      // economy.site.
      4 => 5,
      // execoffice.site.
      5 => 7,
      // education.site.
      6 => 6,
      // finance.site.
      7 => 2,
      // health.site.
      8 => 8,
      // infrastructure.site.
      9 => 9,
      // dcal.vm.
      10 => -1,
      // doe.vm.
      11 => -1,
      // justice.site.
      12 => 10,
      // communities.site.
      13 => 4,
    ];

    if (array_key_exists($domain_id, $map)) {
      return $map[$domain_id];
    }

    return -1;
  }

  /**
   * Batch callback to create the dataset for processing.
   *
   */
  public static function createNodeData($args, &$context) {
    $bundle = $args['bundle'];
    $migration_table = 'migrate_map_node_' . $bundle;
    $limit = $args['limit'];
    $dbConn = \Drupal::database();
    $offset = (!empty($context['sandbox']['offset'])) ? $context['sandbox']['offset'] : 0;

    // Check the mapping table for the bundle exists.
    if (!$dbConn->schema()->tableExists($migration_table)) {
      throw new \Exception("$migration_table table not found");
    }

    // Determine the dataset size.
    if (!isset($context['sandbox']['total'])) {
      $context['sandbox']['total'] = \Drupal::database()
        ->select($migration_table)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    // Retrieve the nid and domains from the migration map table for the bundle.
    $query = $dbConn->select($migration_table, 'mt');
    $query->addField('mt', 'sourceid3', 'domains');
    $query->addField('mt', 'destid1', 'nid');
    $query->range($offset, $limit);
    $result = $query->execute();

    $results = $result->fetchAll();

    // Add to the results array, mapping nid and converting domains to groups.
    $context['results'] = array_reduce($results,
      function ($carry, $object) {
        $carry[$object->nid] = array_map(fn($domain) => self::domainIdtoGroupId($domain[0]), explode('-', $object->domains));
        return $carry;
      }, $context['results']
    );

    // Update offset and current batch.
    $context['sandbox']['offset'] = $offset + $limit;
    $context['finished'] = 0;
    if ($context['sandbox']['offset'] >= $context['sandbox']['total']) {
      $context['finished'] = 1;
    }

    $context['message'] = t(
      'Processed @offset nodes of @total',
      [
        '@offset' => $context['sandbox']['offset'],
        '@total' => $context['sandbox']['total'],
      ]
    );
  }

  /**
   * Batch callback to create Node to Group relationships.
   */
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

      if ($node === NULL) {
        continue;
      }

      foreach ($groups as $group) {
        // Retired or not found domain/group.
        if ($group < 0) {
          continue;
        }

        // All groups.
        if ($group === 0) {
          // Hardcoded the groups to load, not ideal.
          $all_groups = Group::loadMultiple(range(1,10));
          foreach ($all_groups as $gr) {
            $gr->addContent($node);
          }
          continue;
        }

        $group = Group::load($group);
        $group->addContent($node, 'group_node:' . $bundle);
      }

      $count++;

      // Remove processed item from the dataset.
      unset($context['results'][$nid]);
      if ($count >= $limit) {
        break;
      }
    }

    $context['message'] = t(
      'Creating @bundle relationships, @total remaining.', [
        '@bundle' => $bundle,
        '@total' => count($context['results'])
      ]
    );

    $context['finished'] = empty($context['results']);

    if ($context['finished']) {
      $context['results'] = $context['sandbox']['total'];
    }
  }

  /**
   * Batch finish callback.
   */
  public static function finishProcess($success, $results, $operations) {

    if ($success) {
      $message = t('Update process of @count articles was completed.', [
        '@count' => $results
      ]);
      \Drupal::messenger()->addMessage($message);
    }
    else {
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      \Drupal::messenger()->addError($message);
    }
  }

}
