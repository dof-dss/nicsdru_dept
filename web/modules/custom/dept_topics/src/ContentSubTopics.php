<?php

namespace Drupal\dept_topics;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\node\NodeInterface;

/**
 * A utility class to provide easy access to a node's topics/subtopics.
 * Intended to reduce code duplication between backend and frontend
 * preprocessing.
 */
class ContentSubTopics {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor for ContentSubTopic class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param int|\Drupal\node\NodeInterface $node
   *   The subtopic node (nid or full object)
   *
   * @return array
   *   A render array of links elements.
   */
  public function getSubtopicContent(int|NodeInterface $node): array {
    $content = [];

    if (empty($node)) {
      return $content;
    }

    if (is_int($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    if ($node instanceof NodeInterface && $node->bundle() === 'subtopic') {
      // A common table expression (CTE) is used here to paper over an especially
      // awkward three-part data set when assembling what content to show.
      // We can't rely exclusively on the draggableviews table because new nodes
      // with a matching subtopic field value aren't saved to this table until
      // the order is changed and saved by an admin. So in effect, we're doing this:
      // - Create a big single table structure that wraps up:
      //   - Anything from the draggable_views table with that subtopic id arg
      //   - Any articles tagged with that subtopic id value.
      //   - Any subtopics tagged with that subtopic id value but uses
      //     a different field to join on from the above.
      // - Select distinct everything from the CTE and order it
      //   so any draggable views ordered stuff comes first and new stuff
      //   sits underneath that.
      $cte_sql = "WITH content_stack_cte (nid, type, title, weight) AS (
        SELECT
          nfd.nid,
          nfd.type,
          nfd.title,
          ds.weight
        FROM {draggableviews_structure} ds
        JOIN {node_field_data} nfd ON nfd.nid = ds.entity_id
        WHERE ds.view_name = 'content_stacks'
          AND ds.view_display = 'subtopic_articles'
          AND ds.args = :subtopic_id_arg_format
          AND nfd.status = 1
        UNION
        SELECT
          st_nfd.nid,
          st_nfd.type,
          st_nfd.title,
          99 as weight
        FROM {node_field_data} st_nfd
        JOIN {node__field_parent_subtopic} nfps ON st_nfd.nid = nfps.entity_id
        WHERE st_nfd.type = 'subtopic' AND nfps.field_parent_subtopic_target_id = :subtopic_id
        AND st_nfd.status = 1
        UNION
        SELECT
          ar_nfd.nid,
          ar_nfd.type,
          ar_nfd.title,
          99 as weight
        FROM {node_field_data} ar_nfd
        JOIN {node__field_site_subtopics} nfss ON ar_nfd.nid = nfss.entity_id
        WHERE ar_nfd.type = 'article' AND nfss.field_site_subtopics_target_id = :subtopic_id
        AND ar_nfd.status = 1
        )
        SELECT DISTINCT nid, type, title FROM content_stack_cte
        ORDER BY weight, title
      ";
      $subtopic_content = \Drupal::database()
        ->query($cte_sql, [
          ':subtopic_id_arg_format' => '["' . $node->id() . '"]',
          ':subtopic_id' => $node->id()
        ])->fetchAll();

      foreach ($subtopic_content as $row) {
        $content[$row->nid] = Link::createFromRoute($row->title, 'entity.node.canonical', ['node' => $row->nid])->toString();
      }
    }

    return $content;
  }

}
