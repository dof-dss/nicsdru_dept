<?php

namespace Drupal\dept_core\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * EventSubscriber to handle anonymous 403s and convert them to
 * return a fast_404. This reduces the overhead of returning a themed
 * 403 page on high traffic sites.
 */
class Anonymous403Fast404Subscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface.
   */
  public function __construct(AccountProxyInterface $current_user, ConfigFactoryInterface $config_factory) {
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event object.
   */
  public function onException(ExceptionEvent $event) {
    $exception = $event->getThrowable();

    if ($exception instanceof AccessDeniedHttpException && $this->currentUser->isAnonymous()) {
      $request = $event->getRequest();
      $path = '/' . ltrim($request->getPathInfo(), '/');

      // Exclude user routes so login/redirect still works.
      if (preg_match('#^/user(/|$)#', $path)) {
        return;
      }

      // Load fast_404 html from config or fall back to a basic
      // 404 message.
      $performance_config = $this->configFactory->get('system.performance');
      $fast_404 = $performance_config->get('fast_404') ?? [];
      $html = $fast_404['html'] ?? <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Page not found</title>
</head>
<body>
  <h1>Page not found</h1>
  <p>The requested URL was not found on this server.</p>
</body>
</html>
HTML;

      // Return fast 404 instead of a themed 403.
      $response = new Response($html, 404);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::EXCEPTION => ['onException', 100],
    ];
  }

}
