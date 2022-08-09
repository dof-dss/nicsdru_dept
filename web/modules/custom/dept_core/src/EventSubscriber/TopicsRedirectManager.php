<?php

namespace Drupal\dept_core\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TopicsRedirectManager implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbconn;

  /**
   * @var array
   */
  protected $routePatterns;

  /**
   * @param \Drupal\Core\Database\Connection $dbconn
   *   Service container db connection.
   * @param array $route_patterns
   *   Route patterns to match.
   */
  public function __construct(Connection $dbconn, array $route_patterns) {
    $this->dbconn = $dbconn;
    $this->routePatterns = $route_patterns;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event object.
   */
  public function onKernelRequestCheckTopicRedirect(RequestEvent $event): void {
    $request = clone $event->getRequest();
    $path = $request->getPathInfo();

    foreach ($this->routePatterns as $pattern) {
      $matches = [];
      if (preg_match('|' . $pattern . '|', $path, $matches)) {
        $type = $matches[1];
        $entity_id = $matches[2];

        // Lookup the topic/subtopic node id from the migrate map table.
        $new_id = $this->findTopicId($entity_id, $type);

        if (!empty($new_id)) {
          $new_path = preg_replace('|\d+|', $new_id, $path);

          // Create a new Url and redirect to it.
          $url = Url::fromUserInput($new_path)->toString();
          $headers = [
            'X-Redirect-Initiator' => 'TopicsRedirectManager',
          ];

          $response = new RedirectResponse($url, 301, $headers);
          $event->setResponse($response);
        }
      }
    }
  }

  /**
   * @param int $d7_id
   *   The legacy (sub)topic id.
   * @param string $type
   *   Whether it's a topic or subtopic node.
   *
   * @return int
   *   The id corresponding to the legacy id.
   */
  private function findTopicId(int $d7_id, string $type) {
    if (empty($d7_id) || empty($type)) {
      return 0;
    }

    $table = "{migrate_map_node_$type}";
    $result = $this->dbconn->query("SELECT destid1 FROM $table WHERE sourceid2 = :id", [':id' => $d7_id]);
    $new_id = $result->fetchCol('destid1')[0] ?? 0;

    return $new_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // One higher than what's in redirect/src/EventSubscriber/RedirectRequestSubscriber.php.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestCheckTopicRedirect', 34];
    return $events;
  }

}
