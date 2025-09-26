<?php

declare(strict_types=1);

namespace Drupal\Tests\dept_path_trap\Unit;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\dept_path_trap\EventSubscriber\PathTrap404Subscriber;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @coversDefaultClass \Drupal\dept_path_trap\EventSubscriber\PathTrap404Subscriber
 *
 * These unit tests assert:
 * 1) A real node alias (e.g. "/news/murphy-...") is allowed (no 404).
 * 2) A views base path ("/news") is allowed (no 404).
 * 3) A non-alias under a views base ("/news/not-an-alias") 404s.
 * 4) A bogus contextual arg under a views base ("/news/1234") 404s.
 */
final class PathTrap404SubscriberTest extends UnitTestCase {

  /** @var AliasManagerInterface&MockObject */
  private AliasManagerInterface $aliasManager;

  /** @var LanguageManagerInterface&MockObject */
  private LanguageManagerInterface $languageManager;

  /** @var RouteProviderInterface&MockObject */
  private RouteProviderInterface $routeProvider;

  private PathTrap404Subscriber $subscriber;

  protected function setUp(): void {
    parent::setUp();

    $this->aliasManager = $this->createMock(AliasManagerInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->routeProvider = $this->createMock(RouteProviderInterface::class);

    // Language setup: only 'en'.
    $lang = new Language(['id' => 'en']);
    $this->languageManager
      ->method('getCurrentLanguage')
      ->willReturn($lang);
    $this->languageManager
      ->method('getLanguages')
      ->willReturn(['en' => $lang]);

    $this->subscriber = new PathTrap404Subscriber(
      $this->aliasManager,
      $this->languageManager,
      $this->routeProvider
    );
  }

  /**
   * Helper to create a RequestEvent for a given absolute path.
   *
   * @param string $path  e.g. "/news/1234" or "news/1234"
   * @param array|string $query  Either an array (will be http_build_query'ed) or a raw query string.
   */
  private function eventFor(string $path, array|string $query = []): RequestEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);

    $scheme = 'https';
    $host   = 'www.finance-ni.gov.uk';
    $normalizedPath = '/' . ltrim($path, '/');

    if (is_array($query)) {
      $queryString = http_build_query($query);
    }
    else {
      $queryString = ltrim((string) $query, '?');
    }

    $uri = sprintf('%s://%s%s%s',$scheme, $host, $normalizedPath, $queryString !== '' ? '?' . $queryString : '');

    $request = Request::create(
      uri: $uri,
      method: 'GET',
      parameters: is_array($query) ? $query : []
    );

    return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
  }

  /**
   * Configure the route provider so that "/news" is a Views page base,
   * and "/news/feed" is an exact Views route (allowed subpath).
   */
  private function mockViewsRoutes(): void {
    $collectionNews = new RouteCollection();
    $routeNews = new Route(
      '/news',
      [
        '_controller' => '\Drupal\views\Routing\ViewPageController::handle',
        'view_id' => 'news_search',
        'display_id' => 'news_search',
      ]
    );
    $collectionNews->add('view.news_search.news_search', $routeNews);

    $collectionNewsFeed = new RouteCollection();
    $routeFeed = new Route(
      '/news/feed',
      [
        '_controller' => '\Drupal\views\Routing\ViewPageController::handle',
        'view_id' => 'news_search',
        'display_id' => 'news_feed',
      ]
    );
    $collectionNewsFeed->add('view.news_search.news_feed', $routeFeed);

    // Return collections based on the pattern requested.
    $this->routeProvider
      ->method('getRoutesByPattern')
      ->willReturnCallback(function (string $pattern) use ($collectionNews, $collectionNewsFeed) {
        if ($pattern === '/news') {
          return $collectionNews;
        }
        if ($pattern === '/news/feed') {
          return $collectionNewsFeed;
        }
        // No routes for other patterns.
        return new RouteCollection();
      });
  }

  /**
   * Default alias manager behavior: identity (no alias),
   * unless explicitly overridden in a test.
   */
  private function mockAliasIdentity(): void {
    // 1) Define a readable callback that just returns the input alias.
    //    This mirrors Drupal's behavior when no path alias exists: the
    //    alias manager gives you back exactly what you passed in.
    $identityCallback = static function (string $alias, string $langcode): string {
      return $alias;
    };

    // 2) Apply that callback to the mocked service method.
    $this->aliasManager
      ->method('getPathByAlias')
      ->willReturnCallback($identityCallback);
  }

  /**
   * 1) Request for node content path alias: returns ok.
   *
   * e.g. "/news/murphy-establishes-fiscal-council-and-commission" is a real alias
   * -> subscriber should allow it.
   *
   * @covers ::onRequest
   */
  public function testNodeAliasIsAllowed(): void {
    $this->mockViewsRoutes();

    // Make the full path resolve to a system path -> alias exists.
    $this->aliasManager
      ->method('getPathByAlias')
      ->willReturnCallback(static function (string $alias, string $langcode) {
        if ($alias === '/news/murphy-establishes-fiscal-council-and-commission') {
          return '/node/99';
        }

        // Otherwise identity.
        return $alias;
      });

    $event = $this->eventFor('/news/murphy-establishes-fiscal-council-and-commission');

    // Should NOT throw (allowed).
    $this->subscriber->onRequest($event);
    $this->assertTrue(true, 'Alias path passed without 404.');
  }

  /**
   * 2) Request for a view path ("/news"): returns ok.
   *
   * @covers ::onRequest
   */
  public function testViewsBaseIsAllowed(): void {
    $this->mockViewsRoutes();
    $this->mockAliasIdentity();

    $event = $this->eventFor('/news');

    // Should NOT throw (allowed).
    $this->subscriber->onRequest($event);
    $this->assertTrue(true, 'Views base path passed without 404.');
  }

  /**
   * 3) Request for a node content path alias that doesn't match an alias
   *    nor a view path under the same base: 404.
   *
   * We simulate "/news/not-an-alias" where:
   *  - Full path is NOT a real alias.
   *  - Base "/news" IS a Views page.
   *  => Subscriber should throw 404.
   *
   * @covers ::onRequest
   */
  public function testNonAliasUnderViewsBase404s(): void {
    $this->mockViewsRoutes();
    $this->mockAliasIdentity();

    $event = $this->eventFor('/news/not-an-alias');

    $this->expectException(NotFoundHttpException::class);
    $this->subscriber->onRequest($event);
  }

  /**
   * 4) Request for a view path with bogus contextual args ("/news/1234"): 404.
   *
   * @covers ::onRequest
   */
  public function testBogusContextUnderViewsBase404s(): void {
    $this->mockViewsRoutes();
    $this->mockAliasIdentity();

    $event = $this->eventFor('/news/1234');

    $this->expectException(NotFoundHttpException::class);
    $this->subscriber->onRequest($event);
  }

}
