<?php

namespace Drupal\dept_core\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\dept_core\Department;
use Drupal\dept_core\DepartmentManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This subscriber class allows us to shut off access to, or redirect,
 * requests which on one group might be unwanted on another.
 *
 * Eg: northernireland.gov.uk/news (should be /press-releases)
 * or daera-ni.gov.uk/press-releases (should be /news)
 */
class DepartmentPathSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected $departmentManager;

  /**
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   Department manager service object.
   */
  public function __construct(DepartmentManager $department_manager) {
    $this->departmentManager = $department_manager;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event object.
   */
  public function onKernelResponseCheckGroupPath(ResponseEvent $event): void {
    $request = clone $event->getRequest();
    $path = $request->getPathInfo();

    // What's the detected department/group?
    /** @var \Drupal\dept_core\Department $active_dept */
    $active_dept = $this->departmentManager->getCurrentDepartment();
    if (!$active_dept instanceof Department) {
      return;
    }

    /** @var \Drupal\Core\Cache\CacheableResponse $response */
    $response = $event->getResponse();

    if ($active_dept->name() === 'NIGov') {
      // NIGOV would use /press-releases instead.
      if (preg_match('|^/news|', $path)) {
        $response->setStatusCode(404);
        $cache_options = new CacheableMetadata();
        $cache_options->setCacheContexts(['url.site']);
        $response->addCacheableDependency($cache_options);
        $event->setResponse($response);
      }
    }
    else {
      // Non-NIGOV sites would use /news so return 404 for this path.
      if (preg_match('|^/press-releases|', $path)) {
        $response->setStatusCode(404);
        $cache_options = new CacheableMetadata();
        $cache_options->setCacheContexts(['url.site']);
        $response->addCacheableDependency($cache_options);
        $event->setResponse($response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onKernelResponseCheckGroupPath', 5];
    return $events;
  }

}
