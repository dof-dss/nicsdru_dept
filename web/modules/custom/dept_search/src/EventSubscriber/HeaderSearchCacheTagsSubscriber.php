<?php

namespace Drupal\dept_search\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\dept_search\Cache\BroadSearchCacheTags;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Removes content-list cache tags added by the global header search form.
 */
class HeaderSearchCacheTagsSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the response subscriber.
   */
  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly AdminContext $adminContext,
  ) {}

  /**
   * Removes broad Search API list tags from non-search page responses.
   *
   * Search API-based view page responses use custom domain-specific cache tags
   * and are not affected by this subscriber.
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
    if ($this->routeMatch->getRouteName() === 'search_api_autocomplete.autocomplete'
      || $this->adminContext->isAdminRoute()) {
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
