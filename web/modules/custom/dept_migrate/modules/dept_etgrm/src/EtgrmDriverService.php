<?php

namespace Drupal\dept_etgrm;

use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity To Group Relationship Manager service.
 */
class EtgrmDriverService {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * Drupal\Core\Logger\LoggerChannelFactory definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')
    );
  }

  /**
   * Constructs a new instance of this object.
   */
  public function __construct(LoggerChannelFactory $logger) {
    $this->logger = $logger->get('dept_migrate');
    $this->dbconn = Database::getConnection('default', 'default');
  }

  /**
   * Service function to regenerate group relation entities
   * for a specific type of node. Bypasses group and Drupal
   * entity APIs as they're far too slow for the volume of
   * data/operations going on here.
   *
   * @param string $type
   *   Drupal node bundle machine key, eg: page, article.
   */
  public function rebuildRelationsByType(string $type) {
    $type_map = [
      'actions' => 'group_content_type_ef01e89809ca7',
      'application' => 'group_content_type_9dbed154ced4f',
      'article' => 'group_content_type_8f3c8c40c5ced',
      'collection' => 'department_site-group_node-book',
      'case_study' => 'group_content_type_729499773bd55',
      'easychart' => 'group_content_type_85765319814ca',
      'consultation' => 'group_content_type_fb2d5fb87aade',
      'contact' => 'group_content_type_806d1de5fafe5',
      'gallery' => 'group_content_type_671a55a120b42',
      'global_page' => 'group_content_type_34099e0cf683b',
      'heritage_site' => 'group_content_type_4206bea64afae',
      'infogram' => 'group_content_type_6061d9dc53978',
      'landing_page' => 'group_content_type_1b4b1ed9339c4',
      'link' => 'department_site-group_node-link',
      'news' => 'department_site-group_node-news',
      'page' => 'department_site-group_node-page',
      'profile' => 'group_content_type_d17c35c98baa3',
      'project' => 'group_content_type_85d66e53e8361',
      'protected_area' => 'group_content_type_ec8e415306531',
      'publication' => 'group_content_type_d91f8322473a4',
      'subtopic' => 'group_content_type_9741084175ea2',
      'topic' => 'department_site-group_node-topic',
      'ual' => 'department_site-group_node-ual',
      'webform' => 'group_content_type_0b612c56d0b26',
    ];
    $map_table = 'migrate_map_node_' . $type;

    if (!$this->dbconn->schema()->tableExists($map_table)) {
      return;
    }
    // Delete any data we already have in use by joining on the map table.
    // Multi-table delete is a MySQL specific feature used for convenience.
    // NB: this won't remove content relation entities added outside of
    // migrations; the join on the map table restricts the deletion
    // to those records which satisfy the query condition.
    $delete_query = $this->dbconn->query("
      DELETE group_content, group_content_field_data
      FROM group_content
      JOIN group_content_field_data ON group_content.id = group_content_field_data.id
      JOIN $map_table ON $map_table.destid1 = group_content_field_data.entity_id
    ")->execute();

    // Array to represent content to add into the group_content table.
    $map_group_content = [];
    $results = $this->dbconn->query("SELECT
      :gc_type as type,
      :langcode as langcode,
      nfd.uid,
      mt.sourceid3,
      n.nid as entity_id,
      nfd.title as label,
      nfd.created,
      nfd.changed,
      :default_langcode as default_langcode
      FROM {node} n
      JOIN {node_field_data} nfd ON nfd.nid = n.nid
      JOIN $map_table mt ON mt.destid1 = n.nid
      WHERE n.type = :type
    ", [
      ':gc_type' => $type_map[$type],
      ':langcode' => 'en',
      ':type' => $type,
      ':default_langcode' => 1,
    ]);

    foreach ($results as $row) {
      // Begin building a big array of data to start adding to group_content* tables.
      // If there are multiple domains listed, we need to create extra rows
      // in the array we use to populate the tables.
      $domains = [];
      if (preg_match('/-/', $row->sourceid3)) {
        $domains = explode('-', $row->sourceid3);
      }
      else {
        $domains[] = $row->sourceid3;
      }

      $group_item = [];
      foreach ($domains as $domain_id) {
        $group_item['type'] = $row->type;
        $group_item['langcode'] = $row->langcode;
        $group_item['uid'] = $row->uid;
        $group_item['gid'] = \Drupal::service('dept_migrate.migrate_support')->domainIdToGroupId($domain_id);
        $group_item['entity_id'] = $row->entity_id;
        $group_item['label'] = $row->label;
        $group_item['created'] = $row->created;
        $group_item['changed'] = $row->changed;
        $group_item['default_langcode'] = $row->default_langcode;
        // Use last insert id to fill in this value, which links together
        // group_content and group_content_field_data values.
        $group_item['id'] = $this->dbconn->insert('group_content')
          ->fields([
            'type' => $group_item['type'],
            'uuid' => \Drupal::service('uuid')->generate(),
            'langcode' => $group_item['langcode']
          ])->execute();

        // Add record in group_content_field_data.
        $this->dbconn->insert('group_content_field_data')
          ->fields([
            'id' => $group_item['id'],
            'type' => $group_item['type'],
            'langcode' => $group_item['langcode'],
            'uid' => $group_item['uid'],
            'gid' => $group_item['gid'],
            'entity_id' => $group_item['entity_id'],
            'label' => $group_item['label'],
            'created' => $group_item['created'],
            'changed' => $group_item['changed'],
            'default_langcode' => $group_item['default_langcode']
          ])->execute();
      }
    }
  }

  /**
   * @param int $content_id
   *   The content id value.
   * @param int $group_id
   *   The group id value.
   * @param array $collection
   *   The collection we want to examine.
   *
   * @return bool
   *   Whether the collection already contains this key.
   */
  private function groupContainsNode(int $content_id, int $group_id, array &$collection) {
    $key = $this->createCollectionKey($content_id, $group_id);
    return in_array($key, $collection);
  }

  /**
   * @param int $content_id
   *   The numerical id of the content.
   * @param int $group_id
   *   The numerical id of the group.
   *
   * @return string
   *   The delimited, unique key for this collection entry.
   */
  private function createCollectionKey(int $content_id, int $group_id) {
    return $content_id . '/' . $group_id;
  }

}
