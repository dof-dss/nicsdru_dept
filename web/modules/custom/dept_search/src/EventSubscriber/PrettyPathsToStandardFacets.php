<?php

namespace Drupal\dept_search\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Responds to legacy pretty-paths based facet paths and redirects them
 * to the corresponding standard, url encoded facets used on the site now.
 */
class PrettyPathsToStandardFacets implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Higher value than what's in redirect/src/EventSubscriber/RedirectRequestSubscriber.php.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestRewritePrettyPathsFacets', 35];
    return $events;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event object.
   */
  public function onKernelRequestRewritePrettyPathsFacets(RequestEvent $event): void {
    $request = clone $event->getRequest();
    $path = $request->getPathInfo();

    if (preg_match('#\/publications\/(type|topic|date)\/#', $path)) {
      // Break apart the pairs of facet key/value parts.
      // Remove leading /publications and split the path into segments.
      $path = str_replace('/publications', '', $path);
      $segments = explode('/', trim($path, '/'));
      $facetKeyValuePairs = [];

      // Loop through segments and create key-value pairs.
      for ($i = 0; $i < count($segments) - 1; $i += 2) {
        if (!empty($segments[$i + 1])) {
          $facetKeyValuePairs[$segments[$i]] = $segments[$i + 1];
        }
      }

      $redirect_path = '/publications';
      $facet_count = 0;

      // Example target pattern: /publications?f[0]=date:2024&f[1]=type:circulars".
      foreach ($facetKeyValuePairs as $key => $value) {
        if ($facet_count === 0) {
          $redirect_path .= '?';
        }

        $redirect_path .= 'f[' . $facet_count . ']=' . $key . ':' . $value;

        if ($facet_count < count($facetKeyValuePairs)) {
          $redirect_path .= '&';
        }

        $facet_count++;
      }

      if ($facet_count > 0) {
        $url = Url::fromUserInput($redirect_path);
        $headers = [
          'X-Redirect-Initiator' => get_class($this),
        ];

        $response = new RedirectResponse($url->toString(), 301, $headers);
        $event->setResponse($response);
      }
    }
  }

}
