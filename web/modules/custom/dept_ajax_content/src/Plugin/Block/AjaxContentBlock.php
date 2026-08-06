<?php

namespace Drupal\dept_ajax_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_core\DepartmentManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that fetches and displays items from a JSON API endpoint.
 *
 * @Block(
 *   id = "dept_ajax_content",
 *   admin_label = @Translation("Ajax Content"),
 *   category = @Translation("Departmental sites"),
 * )
 */
class AjaxContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Cache ID prefix for API responses.
   */
  const CACHE_ID_PREFIX = 'dept_ajax_content:';

  /**
   * Default cache lifetime in seconds (15 minutes).
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
   * The department manager.
   *
   * @var \Drupal\dept_core\DepartmentManager
   */
  protected DepartmentManager $departmentManager;

  /**
   * Constructs an AjaxContentBlock instance.
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
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The department manager service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ClientInterface $http_client,
    CacheBackendInterface $cache,
    LoggerInterface $logger,
    DepartmentManager $department_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->cache = $cache;
    $this->logger = $logger;
    $this->departmentManager = $department_manager;
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
      $container->get('department.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'api_url' => '',
      'item_count' => 3,
      'view_all_url' => '',
      'cache_lifetime' => self::CACHE_LIFETIME,
      'template' => 'ajax-content',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#description' => $this->t(
        "Full URL or root-relative path of the JSON endpoint (e.g. /api/news/latest). A root-relative path will be prefixed with the current department's domain."
      ),
      '#default_value' => $this->configuration['api_url'],
      '#maxlength' => 512,
      '#required' => TRUE,
    ];

    $form['item_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of items to display'),
      '#description' => $this->t("Ensure the endpoint's pager limit is at least this value."),
      '#default_value' => $this->configuration['item_count'],
      '#min' => 1,
      '#max' => 20,
      '#required' => TRUE,
    ];

    $form['view_all_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"More" link URL'),
      '#description' => $this->t('URL for the link shown below the list. Leave empty to hide it.'),
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

    $form['template'] = [
      '#type' => 'select',
      '#title' => $this->t('Template'),
      '#description' => $this->t('Twig template used to render the block. Add .html.twig files to the module templates directory and rebuild the cache to add options.'),
      '#options' => $this->getTemplateOptions(),
      '#default_value' => $this->configuration['template'],
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
    $this->configuration['template'] = $form_state->getValue('template');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $items = $this->fetchItems();

    if (empty($items)) {
      return [];
    }

    $items = array_slice($items, 0, (int) $this->configuration['item_count']);

    return [
      '#theme' => $this->resolveThemeHook(),
      '#items' => $items,
      '#view_all_url' => $this->configuration['view_all_url'] ?: NULL,
      '#cache' => [
        'max-age' => (int) $this->configuration['cache_lifetime'],
        'contexts' => ['url.site'],
        'tags' => ['dept_ajax_content'],
      ],
    ];
  }

  /**
   * Fetches items from the API endpoint, with optional caching.
   *
   * @return array
   *   A flat array of item arrays from the JSON response, or empty on failure.
   */
  protected function fetchItems(): array {
    $url = $this->resolveApiUrl();

    if (empty($url)) {
      $this->logger->error(
        'Ajax Content block has no valid API URL. Configure the API URL in the block settings.'
      );
      return [];
    }

    $lifetime = (int) $this->configuration['cache_lifetime'];
    $domain_id = $this->departmentManager->getCurrentDepartment()->id();
    // Include domain_id so entries are scoped per-domain even when two domains
    // share the same api_url configuration value.
    $cache_id = self::CACHE_ID_PREFIX . $domain_id . ':' . md5($url);

    if ($lifetime > 0 && ($cached = $this->cache->get($cache_id))) {
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

      if ($lifetime > 0) {
        $cache_tag = 'dept_ajax_content:' . $domain_id;
        $this->cache->set($cache_id, $items, time() + $lifetime, [$cache_tag]);
      }

      return $items;
    }
    catch (RequestException $e) {
      $this->logger->error(
        'Failed to fetch content from %url: @message',
        ['%url' => $url, '@message' => $e->getMessage()]
      );
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Unexpected error fetching content from %url: @message',
        ['%url' => $url, '@message' => $e->getMessage()]
      );
    }

    return [];
  }

  /**
   * Returns the theme hook name for the configured template.
   *
   * @return string
   *   Theme hook in the form dept_ajax_content__{template_name}.
   */
  protected function resolveThemeHook(): string {
    $template = $this->configuration['template'] ?: 'ajax-content';
    return 'dept_ajax_content__' . str_replace('-', '_', $template);
  }

  /**
   * Builds select options from .html.twig files in the templates directory.
   *
   * The label for each option is taken from the @title annotation in the
   * template's docblock comment. If no @title is present, the filename is
   * title-cased and used as a fallback.
   *
   * @return array
   *   An options array suitable for a '#type' => 'select' element.
   */
  protected function getTemplateOptions(): array {
    $options = [];
    $templates_dir = dirname(__DIR__, 3) . '/templates';

    foreach (glob($templates_dir . '/*.html.twig') as $file) {
      $template = basename($file, '.html.twig');
      $options[$template] = $this->extractTemplateTitle($file)
        ?? ucwords(str_replace('-', ' ', $template));
    }

    return $options;
  }

  /**
   * Parses the @title annotation from a Twig template's docblock.
   *
   * Reads only the opening comment block of the file for efficiency.
   * Supports both quoted (@title "My label") and unquoted (@title My label)
   * forms.
   *
   * @param string $path
   *   Absolute path to the .html.twig file.
   *
   * @return string|null
   *   The title string, or null if no @title annotation is found.
   */
  protected function extractTemplateTitle(string $path): ?string {
    // 512 bytes covers any realistic opening docblock.
    $head = file_get_contents($path, length: 512);

    if ($head === FALSE) {
      return NULL;
    }

    if (preg_match('/@title\s+"([^"]+)"/', $head, $matches)
      || preg_match('/@title\s+(\S[^\n\r]+)/', $head, $matches)) {
      return trim($matches[1]);
    }

    return NULL;
  }

  /**
   * Resolves a fully-qualified API URL safe to pass to Guzzle.
   *
   * Accepts either a full URL or a root-relative path in configuration.
   * A relative path is prefixed with the current department's canonical URL.
   * Returns an empty string when the URL cannot be resolved.
   *
   * @return string
   *   An absolute URL with http/https scheme, or empty string on failure.
   */
  protected function resolveApiUrl(): string {
    $path = trim($this->configuration['api_url'] ?? '');

    if (empty($path)) {
      return '';
    }

    // Already a fully-qualified URL — return as-is.
    if (preg_match('#^https?://#i', $path)) {
      return $path;
    }

    // Root-relative path: prepend the current department's canonical base URL.
    $department = $this->departmentManager->getCurrentDepartment();

    if ($department === NULL) {
      return '';
    }

    return rtrim($department->url(), '/') . '/' . ltrim($path, '/');
  }

}
