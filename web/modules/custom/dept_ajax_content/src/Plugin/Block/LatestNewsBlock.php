<?php

namespace Drupal\dept_ajax_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that displays the latest news items from the API.
 *
 * @Block(
 *   id = "dept_ajax_content_latest_news",
 *   admin_label = @Translation("Latest News"),
 *   category = @Translation("Departmental sites"),
 * )
 *
 * @see views/view/news Rest: Latest
 */
class LatestNewsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The default path for the news API endpoint.
   */
  const DEFAULT_API_PATH = '/api/news/latest';

  /**
   * Cache ID prefix for news API responses.
   */
  const CACHE_ID_PREFIX = 'dept_ajax_content_latest_news:';

  /**
   * Cache lifetime in seconds (15 minutes).
   */
  const CACHE_LIFETIME = 900;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a LatestNewsBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ClientInterface $http_client,
    CacheBackendInterface $cache,
    LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->cache = $cache;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('logger.channel.dept_ajax_content'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'api_url' => '',
      'item_count' => 3,
      'view_all_url' => '/news',
      'cache_lifetime' => self::CACHE_LIFETIME,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('News API URL'),
      '#default_value' => $this->configuration['api_url'],
      '#maxlength' => 512,
    ];

    $form['item_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of items to display'),
      '#description' => $this->t("NOTE: If the block does not display the requested number of items, ensure that the View's pager item limit is set to at least the requested count."),
      '#default_value' => $this->configuration['item_count'],
      '#min' => 1,
      '#max' => 20,
      '#required' => TRUE,
    ];

    $form['view_all_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"View all news" link URL'),
      '#description' => $this->t('URL for the "View all news" link shown below the list. Leave empty to hide the link.'),
      '#default_value' => $this->configuration['view_all_url'],
      '#maxlength' => 512,
    ];

    $form['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache lifetime (seconds)'),
      '#description' => $this->t('How long to cache API responses. Set to 0 to disable caching.'),
      '#default_value' => $this->configuration['cache_lifetime'],
      '#min' => 0,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['api_url'] = trim($form_state->getValue('api_url'));
    $this->configuration['item_count'] = (int) $form_state->getValue('item_count');
    $this->configuration['view_all_url'] = trim($form_state->getValue('view_all_url'));
    $this->configuration['cache_lifetime'] = (int) $form_state->getValue('cache_lifetime');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $news_items = $this->fetchNewsItems();

    if (empty($news_items)) {
      return [];
    }

    $item_count = (int) $this->configuration['item_count'];
    $news_items = array_slice($news_items, 0, $item_count);

    return [
      '#theme' => 'dept_ajax_content_latest_news',
      '#news_items' => $news_items,
      '#view_all_url' => $this->configuration['view_all_url'] ?: NULL,
      '#cache' => [
        'max-age' => (int) $this->configuration['cache_lifetime'],
        'contexts' => ['url.site'],
        'tags' => ['dept_ajax_content_latest_news'],
      ],
    ];
  }

  /**
   * Fetches news items from the API endpoint, with caching.
   *
   * @return array
   *   An array of news item arrays, or an empty array on failure.
   */
  protected function fetchNewsItems(): array {
    $url = $this->resolveApiUrl();

    if (empty($url)) {
      $this->logger->error(
        'Unable to build a valid API URL for the Latest News block. Configure the API URL in the block settings.'
      );
      return [];
    }

    $domain_id = \Drupal::service('department.manager')->getCurrentDepartment()->id();
    $cache_id = self::CACHE_ID_PREFIX . md5($url);
    // Cache tag as defined in dept_node for news items. see: dept_node_invalidate_latest_news_cache().
    $cache_tag = 'dept_latest_news:' . $domain_id;

    if ($cached = $this->cache->get($cache_id)) {
      return $cached->data;
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 10,
        'headers' => ['Accept' => 'application/json'],
      ]);

      $data = json_decode((string) $response->getBody(), TRUE);
      // The endpoint returns a flat JSON array of item objects.
      $items = is_array($data) ? $data : [];

      $lifetime = (int) $this->configuration['cache_lifetime'];
      $expire = $lifetime > 0 ? time() + $lifetime : CacheBackendInterface::CACHE_PERMANENT;
      $this->cache->set($cache_id, $items, $expire, [$cache_tag]);

      return $items;
    }
    catch (RequestException $e) {
      $this->logger->error(
        'Failed to fetch news from %url: @message',
        ['%url' => $url, '@message' => $e->getMessage()]
      );
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Unexpected error fetching news from %url: @message',
        ['%url' => $url, '@message' => $e->getMessage()]
      );
    }

    return [];
  }

  /**
   * Resolves a fully-qualified API URL safe to pass to Guzzle.
   *
   * Accepts either a full URL or a root-relative path in configuration.
   * If no URL is configured, falls back to the current request host.
   * Returns an empty string when no valid scheme can be determined.
   *
   * @return string
   *   An absolute URL with http/https scheme, or empty string on failure.
   */
  protected function resolveApiUrl(): string {
    $path = trim($this->configuration['api_url'] ?? '');

    if (empty($path)) {
      $path = static::DEFAULT_API_PATH;
    }

    // Already a fully-qualified URL — validate the scheme and return.
    if (preg_match('#^https?://#i', $path)) {
      return $path;
    }

    // Root-relative path: prepend the current request's scheme + host.
    $request = \Drupal::request();
    $scheme = $request->getScheme();
    $host = $request->getHttpHost();

    if (empty($scheme) || empty($host)) {
      return '';
    }

    return $scheme . '://' . $host . '/' . ltrim($path, '/');
  }

}
