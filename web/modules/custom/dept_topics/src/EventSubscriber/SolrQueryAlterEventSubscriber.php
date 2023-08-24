<?php

namespace Drupal\dept_topics\EventSubscriber;

use Drupal\dept_topics\TopicManager;
use Drupal\search_api_solr\Event\PostCreateIndexDocumentEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber class for altering Solr queries. Replaces
 * hook_search_api_solr_documents_alter which is deprecated from
 * search_api_solr:4.3.0.
 */
class SolrQueryAlterEventSubscriber implements EventSubscriberInterface {

  /**
   * The Topic Manager service.
   *
   * @var \Drupal\dept_topics\TopicManager
   */
  protected $topicManager;

  /**
   * Constructor for SolrQueryAlterEventSubscriber object.
   *
   * @param \Drupal\dept_topics\TopicManager $topic_manager
   *   The Topic Manager service.
   */
  public function __construct(TopicManager $topic_manager) {
    $this->topicManager = $topic_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiSolrEvents::POST_CREATE_INDEX_DOCUMENT => 'postCreate',
    ];
  }

  /**
   * Event callback to adjust site topics Solr document values
   * based on the topics hierarchy values.
   */
  public function postCreate(PostCreateIndexDocumentEvent $event): void {
    $item = $event->getSearchApiItem();
    $solarium_doc = $event->getSolariumDocument();

    $topics_field = $item->getField('field_site_topics');
    if (empty($topics_field)) {
      return;
    }

    $topic_nids = [];

    foreach ($topics_field->getValues() as $topic_id) {
      $topic_nids[] = $topic_id;
      $parent_topics = $this->topicManager->getParentNodes($topic_id);

      if (!empty($parent_topics)) {
        foreach ($parent_topics as $topic_nid => $details) {
          $topic_nids[] = $topic_nid;
        }

        $topic_nids = array_unique($topic_nids);
      }
    }

    if (!empty($topic_nids)) {
      // @phpstan-ignore-next-line. Array type is always set, even with 0 length.
      $solr_topics_field_id = count($topic_nids > 1) ? 'itm_field_site_topics' : 'its_field_site_topics';
      // Add to the Solr document.
      // @phpstan-ignore-next-line. Grumbles about setField being missing, but it's part of the Document class.
      $solarium_doc->setField($solr_topics_field_id, $topic_nids);
    }
  }

}
