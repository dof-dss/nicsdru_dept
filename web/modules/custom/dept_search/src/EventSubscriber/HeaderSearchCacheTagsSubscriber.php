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
    if (in_array($route_name, [
      'view.search.site_search',
      'search_api_autocomplete.autocomplete',
    ], TRUE)) {
      return;
    }

    $broad_search_tags = [
      'search_api_autocomplete_search_list:views:search',
      'search_api_list:default_content',
    ];

    $response = $event->getResponse();
    if ($response->headers->has('X-Drupal-Cache-Tags')) {
      $response->headers->set('X-Drupal-Cache-Tags', implode(' ', array_values(array_diff(
        explode(' ', $response->headers->get('X-Drupal-Cache-Tags')),
        $broad_search_tags
      ))));
    }

    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    $metadata = $response->getCacheableMetadata();
    $metadata->setCacheTags(array_values(array_diff(
      $metadata->getCacheTags(),
      $broad_search_tags
    )));
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
