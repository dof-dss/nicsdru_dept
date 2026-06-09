<?php

namespace Drupal\dept_search\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Removes content-list cache tags added by the global header search form.
 */
class HeaderSearchCacheTagsSubscriber implements EventSubscriberInterface {

  /**
   * Search API tag prefixes that cause whole-index public listing purges.
   */
  private const BROAD_SEARCH_TAG_PREFIXES = [
    'search_api_list:',
    'search_api_autocomplete_search_list:views:',
  ];

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

    // Keep autocomplete itself and administration pages fully cache-tagged.
    // Public pages keep entity-specific tags, but not whole-index tags that
    // purge unrelated domains when any indexed content changes.
    if (\Drupal::routeMatch()->getRouteName() === 'search_api_autocomplete.autocomplete'
      || \Drupal::service('router.admin_context')->isAdminRoute()) {
      return;
    }

    $response = $event->getResponse();
    if ($response->headers->has('X-Drupal-Cache-Tags')) {
      $cache_tags_header = $response->headers->get('X-Drupal-Cache-Tags');
      $cache_tags = explode(' ', $cache_tags_header);
      $broad_search_tags = $this->getBroadSearchTags($cache_tags);

      // Keep every existing tag except the broad Search API tags.
      $cache_tags_to_keep = array_values(array_diff($cache_tags, $broad_search_tags));
      $response->headers->set('X-Drupal-Cache-Tags', implode(' ', $cache_tags_to_keep));
    }

    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    $metadata = $response->getCacheableMetadata();
    $metadata->setCacheTags(array_values(array_diff(
      $metadata->getCacheTags(),
      $this->getBroadSearchTags($metadata->getCacheTags())
    )));
  }

  /**
   * Finds broad Search API tags that should not bubble to public pages.
   *
   * @param string[] $tags
   *   Cache tags to inspect.
   *
   * @return string[]
   *   Broad Search API tags.
   */
  private function getBroadSearchTags(array $tags): array {
    return array_values(array_filter($tags, static function (string $tag): bool {
      foreach (self::BROAD_SEARCH_TAG_PREFIXES as $prefix) {
        if (str_starts_with($tag, $prefix)) {
          return TRUE;
        }
      }

      return FALSE;
    }));
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
