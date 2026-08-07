<?php

namespace Drupal\dept_ajax_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dept_core\DepartmentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a placeholder block that loads content from a JSON API via AJAX.
 *
 * The block renders a static HTML placeholder containing the API URL and
 * display options as data attributes. Client-side JavaScript fetches the
 * data and populates the placeholder, allowing the page itself to be served
 * from the full page cache regardless of content changes.
 *
 * @Block(
 *   id = "dept_ajax_content",
 *   admin_label = @Translation("Ajax Content"),
 *   category = @Translation("Departmental"),
 * )
 */
class AjaxContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\dept_core\DepartmentManager $department_manager
   *   The department manager service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    LoggerInterface $logger,
    DepartmentManager $department_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['api_url'] = trim($form_state->getValue('api_url'));
    $this->configuration['item_count'] = (int) $form_state->getValue('item_count');
    $this->configuration['view_all_url'] = trim($form_state->getValue('view_all_url'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $url = $this->resolveApiUrl();

    if (empty($url)) {
      $this->logger->error(
        'Ajax Content block has no valid API URL. Configure the API URL in the block settings.'
      );
      return [];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['ajax-content-block'],
        'data-ajax-url' => $url,
        'data-count' => (int) $this->configuration['item_count'],
        'data-view-all-url' => $this->configuration['view_all_url'] ?: '',
      ],
      '#attached' => [
        'library' => ['dept_ajax_content/ajax-content'],
      ],
      '#cache' => [
        'max-age' => Cache::PERMANENT,
        'contexts' => ['url.site'],
      ],
    ];
  }

  /**
   * Resolves a fully-qualified API URL for use as a template data attribute.
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
