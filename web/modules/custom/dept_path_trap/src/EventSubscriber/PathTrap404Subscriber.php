<?php

namespace Drupal\dept_path_trap\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class PathTrap404Subscriber implements EventSubscriberInterface {

  public function __construct(
    private AliasManagerInterface $aliasManager,
    private LanguageManagerInterface $languageManager,
    private RouteProviderInterface $routeProvider,
  ) {}

  public static function getSubscribedEvents(): array {
    // Run before the router listener (~32). Higher number = earlier.
    return [KernelEvents::REQUEST => ['onRequest', 300]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    // Raw path (what the browser sent), normalised.
    $raw = parse_url($request->getRequestUri(), PHP_URL_PATH) ?? '/';
    $raw = $this->norm($raw);
    if ($raw === '/') {
      return;
    }

    // 1) If the full raw path is an alias, let Drupal handle it (aliases win).
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $decoded  = rawurldecode($raw);
    $sysA = $this->aliasManager->getPathByAlias($raw, $langcode);
    $sysB = ($decoded !== $raw) ? $this->aliasManager->getPathByAlias($decoded, $langcode) : $sysA;
    if ($sysA !== $raw || $sysB !== $decoded) {
      return;
    }

    // 2) Split segments; strip a language prefix if present.
    $segments = $this->segments($raw);
    if (!$segments) {
      return;
    }
    $langIds = array_map(fn($l) => $l->getId(), $this->languageManager->getLanguages());
    if (in_array($segments[0], $langIds, true)) {
      $segments = array_slice($segments, 1);
      if (!$segments) {
        return;
      }
    }

    // Only act on "/{segment1}/{something}".
    if (count($segments) < 2) {
      return;
    }

    // 3) Is "/{segment1}" a Views PAGE display?
    $basePath = '/' . $segments[0];
    if (!$this->isViewsPageBase($basePath)) {
      // Not a Views page base -> don't interfere.
      return;
    }

    // 4) Allow exact, static subpaths that are themselves real Views routes
    //    (e.g. a feed display at "/news/feed"). This stays strict: no params.
    $second = $segments[1];
    $exactTwo = $basePath . '/' . $second;
    if ($this->isExactViewsRoute($exactTwo)) {
      return;
    }

    // Otherwise anything under "/{segment1}/{wildcard}" is invalid -> fast 404.
    throw new NotFoundHttpException();
  }

  /** True if the exact path is backed by a Views *page* display route. */
  private function isViewsPageBase(string $path): bool {
    $path = $this->norm($path);

    // Ask the router for all routes that match this exact path.
    $collection = $this->routeProvider->getRoutesByPattern($path);

    foreach ($collection as $name => $route) {
      if ($route->getPath() !== $path) {
        continue; // only exact path, no params
      }
      $defaults = $route->getDefaults();
      $controller = (string) ($defaults['_controller'] ?? '');

      // Typical Views page signals:
      $isViewsRouteName = str_starts_with((string) $name, 'view.');
      $hasViewsDefaults = isset($defaults['view_id'], $defaults['display_id']);
      $isViewsController = str_contains($controller, '\Drupal\views\Routing\ViewPageController');

      if (($isViewsRouteName || $hasViewsDefaults) && $isViewsController) {
        return true;
      }

      // Some wrappers (e.g. pretty paths) still carry view_id/display_id but
      // use a different controller; in that case, treat it as Views-backed.
      if ($hasViewsDefaults) {
        return true;
      }
    }
    return false;
  }

  /** True if the exact path is a concrete (non-parameter) Views route. */
  private function isExactViewsRoute(string $path): bool {
    $path = $this->norm($path);
    $collection = $this->routeProvider->getRoutesByPattern($path);

    foreach ($collection as $name => $route) {
      if ($route->getPath() !== $path) {
        continue; // only exact path, no params like "/news/{foo}"
      }
      $defaults = $route->getDefaults();
      if (str_starts_with((string) $name, 'view.') || isset($defaults['view_id'], $defaults['display_id'])) {
        return true;
      }
    }
    return false;
  }

  private function norm(string $p): string {
    $p = '/' . ltrim($p, '/');
    return ($p !== '/' && str_ends_with($p, '/')) ? rtrim($p, '/') : $p;
  }

  /** @return string[] */
  private function segments(string $p): array {
    return array_values(array_filter(explode('/', $this->norm($p)), fn($s) => $s !== ''));
  }
}
