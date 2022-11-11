<?php

namespace Drupal\dept_publications\Plugin\views\area;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an area plugin to display a header sort by option.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("publications_sort_by")
 */
class PublicationsSortBy extends AreaPluginBase {
  // Query parameter of search form.
  const KEYWORD_PARAM = 'search';

  // Query parameter created by view for created date field.
  const PUBLISHED_DATE_PARAM = 'field_published_date';

  // Query parameter created by view for relevance field.
  const RELEVANCE_PARAM = 'search_api_relevance';

  // Search view's route name.
  const SEARCH_PAGE_ROUTE = 'view.publications_search.publications_search';

  /**
   * Constructs a new PublicationsSortBy.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Render the area.
   *
   * @param bool $empty
   *   (optional) Indicator if view result is empty or not. Defaults to FALSE.
   *
   * @return array
   *   In any case we need a valid Drupal render array to return.
   */
  public function render($empty = FALSE) {

    if ($empty) {
      return [];
    }

    $request_query = \Drupal::request()->query;

    // Default query options for published date sort criteria.
    $date_options_query = $request_query->all();
    $date_options_query['sort_by'] = self::PUBLISHED_DATE_PARAM;
    $date_options_query['sort_order'] = 'DESC';
    // Drop the page query param from sort links.
    unset($date_options_query['page']);
    $date_options = [
      'query' => $date_options_query,
    ];

    // Default query options for relevance sort criteria.
    $relevance_options_query = $request_query->all();
    $relevance_options_query['sort_by'] = self::RELEVANCE_PARAM;
    $relevance_options_query['sort_order'] = 'DESC';
    // Drop the page query param from sort links.
    unset($relevance_options_query['page']);
    $relevance_options = [
      'query' => $relevance_options_query,
    ];

    // Define the sort options render array.
    $sort_options = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'subtitle view-sort-options',
        ],
      ],
    ];

    // Set a sort label that describes how results
    // are sorted.
    $sort_options['sort_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => t('sorted by date published'),
      '#attributes' => [
        'class' => ['label-inline'],
      ],
    ];

    // Determine which criteria is currently active.
    $active_sort = self::RELEVANCE_PARAM;

    if ($request_query->has('sort_by') && $request_query->get('sort_by') === self::PUBLISHED_DATE_PARAM) {
      $active_sort = self::PUBLISHED_DATE_PARAM;
    }

    // When search query is provided, results by default
    // will be sorted by relevance (otherwise they are
    // sorted by publication date DESC).
    if (!empty($request_query->get(self::KEYWORD_PARAM))) {
      if ($active_sort === self::RELEVANCE_PARAM) {
        $sort_options['sort_label'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => t('sorted by relevance'),
          '#attributes' => [
            'class' => ['label-inline'],
          ],
        ];
        // Link to switch back to publication date sort order.
        $sort_options['sort_link'] = [
          '#type' => 'link',
          '#title' => 'sort by date published',
          '#url' => Url::fromRoute(self::SEARCH_PAGE_ROUTE, [], $date_options),
          '#attributes' => [
            'data-self-ref' => ['false'],
          ],
        ];
      }
      elseif ($active_sort === self::PUBLISHED_DATE_PARAM) {
        $sort_options['sort_label'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => t('sorted by date published'),
          '#attributes' => [
            'class' => ['label-inline'],
          ],
        ];
        // Link to switch back to relevance sort order.
        $sort_options['sort_link'] = [
          '#type' => 'link',
          '#title' => 'sort by relevance',
          '#url' => Url::fromRoute(self::SEARCH_PAGE_ROUTE, [], $relevance_options),
          '#attributes' => [
            'data-self-ref' => ['false'],
          ],
        ];
      }
    }

    return $sort_options;
  }

}

