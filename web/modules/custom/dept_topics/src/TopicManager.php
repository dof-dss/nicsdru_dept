<?php

namespace Drupal\dept_topics;

/**
 * Service class for managing Department topics.
 */
class TopicManager {

  /**
   * Return the parent nodes for a given node ID.
   *
   * @param $node_id
   *   Node ID to return the parents.
   *
   * @return array
   *   Array consisting the level number containing an array of parent node ID's
   *   within that level.
   *
   */
  public function getParents($node_id) {

  }

  /**
   * Return the adjacent node ids for a given node ID.
   *
   * @param $node_id
   *   Node ID to return siblings.
   *
   * @return array
   *   Array of node ID's.
   */
  public function getSiblings($node_id) {

  }

  /**
   * Processes each topic's reference targets to remove stale entries.
   */
  public function cleanTopicReferences() {

  }
}
