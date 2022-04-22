<?php

use Drupal\dept_etgrm\EtgrmBatchService;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\node\Entity\Node;

function buildGroupRelationships($row, $bundle, &$context) {

  $node = Node::load($row->nid);

  if (empty($node)) {
    return;
  }
  $relationships = GroupContent::loadByEntity($node);

  foreach ($relationships as $relation) {
    $node_groups[] = $relation->getGroup()->id();
  }

  foreach (explode('-', $row->domains) as $domain) {
    $node_group = EtgrmBatchService::domainIDtoGroupId($domain);

    if ($node_group == 0) {
      continue;
    } else {
      if (empty($node_groups) || !in_array($node_group, $node_groups)) {
        $group = Group::load($node_group);
        $group->addContent($node, 'group_node:' . $bundle);
        $count++;
      }
    }
  }

  $context['results']['total'] += $count;
  $context['results']['bundle'] = $bundle;
}

function buildGroupRelationshipsFinished($success, $results, $operations) {
  if ($success) {
    \Drupal::messenger()->addMessage(t('@count group relationships created for @bundle', [
      '@count' => $results['total'],
      '@bundle' => $results['bundle'],
    ]));
  }
  else {
    $error_operation = reset($operations);
    \Drupal::messenger()->addError(t('An error occurred while processing @operation with arguments : @args', [
      '@operation' => $error_operation[0],
      '@args' => print_r($error_operation[0], TRUE),
    ]));
  }

}
