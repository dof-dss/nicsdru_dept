<?php

namespace Drupal\dept_search\Plugin\views\area;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines an area plugin to display a header sort by option.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("search_sort_by")
 */
final class SearchSortBy extends AreaPluginBase {

  use StringTranslationTrait;

  /**
   * Query parameter of search form.
   */
  public const KEYWORD_PARAM = 'search';

  /**
   * Query parameter created by view for created date field.
   */
  public const PUBLISHED_DATE_PARAM = 'field_published_date';

  /**
   * Query parameter created by view for relevance field.
   */
  public const RELEVANCE_PARAM = 'search_api_relevance';

  /**
   * Constructs a new SearchSortBy.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RouteMatchInterface $routeMatch,
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('request_stack'),
    );
  }

  /**
   * Render the area.
   *
   * @param bool $empty
   *   (optional) Indicator if view result is empty or not. Defaults to FALSE.
   *
   * @return array
   *   A Drupal render array.
   */
  public function render($empty = FALSE): array {
    if ($empty) {
      return [];
    }

    $request = $this->requestStack->getCurrentRequest();
    $request_query = $request ? $request->query : NULL;

    // Defensive: if there's no current request, we can't build links reliably.
    if (!$request_query) {
      return [];
    }

    $routeName = (string) $this->routeMatch->getRouteName();
    $routeParams = $this->routeMatch->getRawParameters()->all();

    // Default query options for published date sort criteria.
    $date_options_query = $request_query->all();
    $date_options_query['sort_by'] = self::PUBLISHED_DATE_PARAM;
    $date_options_query['sort_order'] = 'DESC';
    unset($date_options_query['page']);
    $date_options = ['query' => $date_options_query];

    // Default query options for relevance sort criteria.
    $relevance_options_query = $request_query->all();
    $relevance_options_query['sort_by'] = self::RELEVANCE_PARAM;
    $relevance_options_query['sort_order'] = 'DESC';
    unset($relevance_options_query['page']);
    $relevance_options = ['query' => $relevance_options_query];

    // Define the sort options render array.
    $sort_options = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['subtitle', 'view-sort-options'],
      ],
    ];

    // If it's the events route then tweak the title to reflect this.
    $date_sort_title = $this->t('date published');
    if ($routeName === 'view.events.events_search') {
      $date_sort_title = $this->t('date of event');
    }

    // Default label (may be replaced below).
    $sort_options['sort_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('sorted by @criteria', ['@criteria' => $date_sort_title]),
      '#attributes' => ['class' => ['label-inline']],
    ];

    // Determine which criteria is currently active.
    $active_sort = self::RELEVANCE_PARAM;
    if ($request_query->has('sort_by') && $request_query->get('sort_by') === self::PUBLISHED_DATE_PARAM) {
      $active_sort = self::PUBLISHED_DATE_PARAM;
    }

    // When search query is provided, results default to relevance.
    if (!empty($request_query->get(self::KEYWORD_PARAM))) {
      if ($active_sort === self::RELEVANCE_PARAM) {
        $sort_options['sort_label']['#value'] = $this->t('sorted by relevance');

        // Link to switch back to publication date sort order.
        $sort_options['sort_link'] = [
          '#type' => 'link',
          '#title' => $this->t('sort by @criteria', ['@criteria' => $date_sort_title]),
          '#url' => Url::fromRoute($routeName, $routeParams, $date_options),
          '#attributes' => [
            'data-self-ref' => ['false'],
          ],
        ];
      }
      elseif ($active_sort === self::PUBLISHED_DATE_PARAM) {
        $sort_options['sort_label']['#value'] = $this->t('sorted by date published');

        // Link to switch back to relevance sort order.
        $sort_options['sort_link'] = [
          '#type' => 'link',
          '#title' => $this->t('sort by relevance'),
          '#url' => Url::fromRoute($routeName, $routeParams, $relevance_options),
          '#attributes' => [
            'data-self-ref' => ['false'],
          ],
        ];
      }
    }

    return $sort_options;
  }

}
