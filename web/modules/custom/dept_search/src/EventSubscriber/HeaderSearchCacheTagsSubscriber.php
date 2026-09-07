<?php

namespace Drupal\dept_search\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\dept_search\Cache\BroadSearchCacheTags;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Removes content-list cache tags added by the global header search form.
 */
class HeaderSearchCacheTagsSubscriber implements EventSubscriberInterface {

  /**
   * Removes broad Search API list tags from non-search page responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event object.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $route_name = \Drupal::routeMatch()->getRouteName();

    // Keep autocomplete itself and administration pages fully cache-tagged.
    // Public pages keep entity-specific tags, but not whole-index tags that
    // purge unrelated domains when any indexed content changes.
    if ($route_name === 'search_api_autocomplete.autocomplete'
      || \Drupal::service('router.admin_context')->isAdminRoute()) {
      return;
    }

    $search_views = [
      'view.news_search.news_search',
      'view.publications_search.publications_search',
      'view.consultations_search.consultations_search',
    ];

    if (in_array($route_name, $search_views, TRUE)) {
      // Do not strip broad search tags from search pages.
      return;
    }

    $response = $event->getResponse();
    if ($response->headers->has('X-Drupal-Cache-Tags')) {
      $cache_tags_header = $response->headers->get('X-Drupal-Cache-Tags');
      $cache_tags = explode(' ', $cache_tags_header);
      $broad_search_tags = BroadSearchCacheTags::findBroadTags($cache_tags);

      // Keep every existing tag except the broad Search API tags.
      $cache_tags_to_keep = array_values(array_diff($cache_tags, $broad_search_tags));
      $response->headers->set('X-Drupal-Cache-Tags', implode(' ', $cache_tags_to_keep));
    }

    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    $metadata = $response->getCacheableMetadata();
    $metadata->setCacheTags(BroadSearchCacheTags::removeBroadTags(
      $metadata->getCacheTags()
    ));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onResponse', 100];
    $events[KernelEvents::RESPONSE][] = ['onResponse', -100];
    return $events;
  }

}
