<?php

/**
 * @file
 * Theme hook_preprocess functions.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;
use Drupal\dept_core\Department;
use Drupal\media\MediaInterface;
use Drupal\nicsdru_dept_theme\Helpers;
use Drupal\nicsdru_dept_theme\LayoutClasses;
use Drupal\node\Entity\Node;

/**
 * Implements hook_preprocess_html().
 */
function nicsdru_dept_theme_preprocess_html(array &$variables) {
  // Set department specific variables.
  $dept = \Drupal::service('department.manager')->getCurrentDepartment();
  if (is_object($dept) && method_exists($dept, 'id')) {
    $variables['attributes']['class'][] = 'dept-' . $dept->id();
    $variables['site_dept_id'] = $dept->id();
  }
  else {
    $variables['attributes']['class'][] = 'dept-admin';
    $variables['site_dept_id'] = 'nigov';
  }
}

/**
 * Implements hook_preprocess_page().
 */
function nicsdru_dept_theme_preprocess_page(array &$variables) {
  // Process node pages.
  if (!empty($variables['node'])) {
    $node = $variables['node'];

    if (method_exists($node, 'bundle')) {
      switch ($node->bundle()) {
        // Specific node types have a main and aside.
        case "application":
        case "article":
        case "news":
        case "page":
        case "protected_area":
        case "gallery":
          // If page is not in a book, it has a main and aside.
          if (empty($node->book)) {
            $variables['main_content_attr']->addClass(LayoutClasses::MAIN_SIDE);
            $variables['sidebar_second_attr']->addClass('ga-aside');
          }
          else {
            // Pages in a book have a main and the sidebar is removed.
            $variables['main_content_attr']->addClass(LayoutClasses::MAIN_LIMITED);
            unset($variables['page']['sidebar_second']);
          }
          break;

        // Specific node types have a main only.
        case "ual":
        case "subtopic":
          $variables['main_content_attr']->addClass(LayoutClasses::MAIN);
          break;

        // All the rest have a main with a limited width.
        default:
          $variables['main_content_attr']->addClass(LayoutClasses::MAIN_LIMITED);
          break;
      }

      // If node has custom layout (landing pages), then hide the related region.
      if ($variables['node']->layout_page &&
        ($node->bundle()=='topic' || $node->bundle()=='subtopic')) {
          unset($variables['page']['related']);
      }
    }
  }

  // Process search view pages.
  $viewpage = \Drupal::routeMatch()->getParameter('view_id');

  if (!empty($viewpage)) {
    switch ($viewpage) {
      case "protected_areas":
        $variables['main_content_attr']->addClass(LayoutClasses::SIDE_MAIN);
        $variables['sidebar_first_attr']
          ->addClass('ga-aside')
          ->setAttribute('role', 'search')
          ->setAttribute('aria-roledescription', t('search protected areas'));
        break;

      case "events":
        $variables['main_content_attr']->addClass(LayoutClasses::SIDE_MAIN);
        $variables['sidebar_first_attr']
          ->addClass('ga-aside')
          ->setAttribute('role', 'search')
          ->setAttribute('aria-roledescription', t('search events'));
        break;

      case "publications_search":
        $variables['main_content_attr']->addClass(LayoutClasses::SIDE_MAIN);
        $variables['sidebar_first_attr']
          ->addClass('ga-aside')
          ->setAttribute('role', 'search')
          ->setAttribute('aria-roledescription', t('search publications'));
        break;

      case "consultations_search":
        $variables['main_content_attr']->addClass(LayoutClasses::SIDE_MAIN);
        $variables['sidebar_first_attr']
          ->addClass('ga-aside')
          ->setAttribute('role', 'search')
          ->setAttribute('aria-roledescription', t('search consultations'));
        break;

      case "press_release_search":
        $variables['main_content_attr']->addClass(LayoutClasses::SIDE_MAIN);
        $variables['sidebar_first_attr']
          ->addClass('ga-aside')
          ->setAttribute('role', 'search')
          ->setAttribute('aria-roledescription', t('search press releases'));
        break;

      case "news_search":
        $variables['main_content_attr']->addClass(LayoutClasses::SIDE_MAIN);
        $variables['sidebar_first_attr']
          ->addClass('ga-aside')
          ->setAttribute('role', 'search')
          ->setAttribute('aria-roledescription', t('search news'));
        break;

      case "search":
        $variables['main_content_attr']->addClass(LayoutClasses::MAIN_LIMITED);
        if ($variables['content_wrapper_attr'] instanceof Attribute) {
          $variables['content_wrapper_attr']
            ->addClass(['ga-main', 'search-content'])
            ->setAttribute('role', 'presentation');
        }
        break;

      case "contacts":
        $variables['main_content_attr']->addClass(LayoutClasses::MAIN_LIMITED);
        if ($variables['content_wrapper_attr'] instanceof Attribute) {
          $variables['content_wrapper_attr']
            ->addClass(['ga-main', 'contacts-content'])
            ->setAttribute('role', 'presentation');
        }
    }
  }

  // Process pages based on routes.
  $route = \Drupal::routeMatch()->getRouteName();

  switch ($route) {
    // News landing page.
    case 'dept_news.news_listing':
      $variables['main_content_attr']->addClass(LayoutClasses::MAIN);
      if ($variables['content_wrapper_attr'] instanceof Attribute) {
        $variables['content_wrapper_attr']
          ->addClass(['ga-main', 'list-content'])
          ->setAttribute('role', 'presentation');
      }
      break;

    // Site theme term pages.
    case 'entity.taxonomy_term.canonical':
      // Set the section css classes for theme taxonomy terms.
      if (array_key_exists('term', $variables) && $variables['term']->bundle() === 'site_themes') {
        $variables['main_content_attr']->addClass([
          'grid',
          'layout--theme-term',
        ]);
        if ($variables['content_wrapper_attr'] instanceof Attribute) {
          $variables['content_wrapper_attr']
            ->addClass(['ga-main', 'list-content'])
            ->setAttribute('role', 'presentation');
        }
        $variables['sidebar_second_attr']->addClass('section--popular');
      }
      break;

    // Webforms.
    case 'entity.webform.canonical':
      $variables['main_content_attr']->addClass(LayoutClasses::MAIN_LIMITED);
      if ($variables['content_wrapper_attr'] instanceof Attribute) {
        $variables['content_wrapper_attr']
          ->addClass(['ga-main', 'form-content'])
          ->setAttribute('role', 'presentation');
      }
      break;

    // Node preview pages.
    // Ensure the side-bar in the non node canonical views are visible to
    // accurately reflect how the published node will look.
    case 'entity.node.preview':
    case 'entity.node.revision':
    case 'entity.node.latest':
      $node_id = \Drupal::routeMatch()->getRawParameter('node');

      if ($node_id === NULL) {
        $node = \Drupal::routeMatch()->getParameter('node_preview');
      }
      else {
        $node = Node::load($node_id);
      }

      $node_type = $node->getType();

      switch ($node_type) {
        case 'application':
        case 'article':
        case 'news':
        case 'page':
        case "protected_area":
        case "gallery":
          if ($node_type === 'news') {
            // Render Featured and Latest news block.
            $variables['page']['sidebar_second'][] = Helpers::blockContent('featurednews');
            $variables['page']['sidebar_second'][] = Helpers::blockContent('views_block__news_latest_news_block');
          }

          break;

        default:
          break;
      }

      // Add classes to main and sidebar regions to listed node types.
      switch ($node_type) {
        case "article":
        case "application":
        case "news":
        case "page":
        case "protected_area":
        case "gallery":
          $variables['main_content_attr']->addClass(LayoutClasses::MAIN_SIDE);
          $variables['sidebar_second_attr']->addClass('ga-aside');
          break;

        case "ual":
        case "consultation":
          $variables['main_content_attr']->addClass(LayoutClasses::MAIN);
          break;

        // All the rest have a main with a limited width.
        default:
          $variables['main_content_attr']->addClass(LayoutClasses::MAIN_LIMITED);
          break;
      }

      break;

    default:
      break;
  }

  if (isset($variables['is_front']) && $variables['is_front'] == TRUE) {
    $front_classes = ['grid', 'layout--front'];
    $variables['main_content_attr']->addClass($front_classes);
    $variables['sidebar_second_attr']->addClass('section--popular');
  }
}

/**
 * Implements hook_preprocess_node().
 */
function nicsdru_dept_theme_preprocess_node(array &$variables) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = &$variables['node'];
  $node_type = $node->getType();

  // Check if the node has a custom layout.
  if ($node->hasField('layout_builder__layout') && !$node->get('layout_builder__layout')->isEmpty()) {
    $variables['has_custom_layout'] = TRUE;
    $variables['node']->layout_page = TRUE;
  }
  else {
    $variables['has_custom_layout'] = FALSE;
    $variables['node']->layout_page = FALSE;
  }

  // Array of bundles for which the social sharing block should be displayed.
  $social_bundles = [
    'application',
    'article',
    'contact',
    'consultation',
    'embargoed_publication',
    'news',
    'application',
    'page',
    'publication',
  ];

  $variables['display_social_links'] = in_array($node->bundle(), $social_bundles);
  // Display the topics link list.
  $variables['show_topics'] = TRUE;
  // Display the title element.
  $variables['show_title'] = TRUE;
  // Hide the title text within the title element.
  $variables['title_text_visible'] = TRUE;
  $variables['title'] = $node->getTitle();
  $variables['node_type'] = $node_type;

  switch ($node_type) {
    case "article":
    case "application":
    case "contact":
    case "news":
    case "publication":
    case "subtopic":
    case "protected_area":
    case "gallery":
      $variables['attributes']['class'][] = Html::cleanCssIdentifier('ga-main');
      $variables['attributes']['class'][] = Html::cleanCssIdentifier('article-content');

      if (!empty($variables['content']['field_attachment'])) {
        $variables['content']['field_attachment']['#title'] = t('Documents');
      }

      break;

    case "ual":
    case "webform":
      $variables['attributes']['class'][] = Html::cleanCssIdentifier('ga-main');
      break;

    case "page":
      // Omit the title on these content types; causes trouble with search
      // indexing. Resolvable with either suitable block config or specific
      // node templates for these types.
      $variables['show_title'] = FALSE;
      break;

    case "heritage_site":
      if (!empty($variables['elements']['#fieldgroups']['group_heritage_site_body'])) {
        $body_group_label = &$variables['elements']['#fieldgroups']['group_heritage_site_body']->label;
        $body_group_label = t('About') . ' ' . $variables['node']->label();
      }
      break;

  }

  // Only use this code for consultations content type.
  if ($node_type === 'consultation') {

    // Get the current time to gauge consultation start and end dates off.
    $current_time = \Drupal::time()->getCurrentTime();

    // Convert consultation start date to a timestamp.
    $consultation_start_date = new DrupalDateTime($variables['node']->get('field_consultation_dates')->start_date);
    $variables['consultation_start_date'] = $consultation_start_date;

    // Convert consultation end date to a timestamp.
    $consultation_end_date = new DrupalDateTime($variables['node']->get('field_consultation_dates')->end_date);
    $variables['consultation_end_date'] = $consultation_end_date;

    // Set a variable to be used in the twig template for the ways to respond fieldgroup, initially set to 0.
    $variables['consultation_in_progress'] = FALSE;
    $variables['consultation_pending'] = FALSE;

    // If the consultation started before and is ending after today's date then show the contact information.
    if ($current_time > $consultation_start_date->getTimestamp() && $current_time <= $consultation_end_date->getTimestamp()) {
      $variables['consultation_in_progress'] = TRUE;
    }
    elseif ($current_time < $consultation_start_date->getTimestamp()) {
      $variables['consultation_pending'] = TRUE;
    }
  }

  if ($node_type === 'event') {
    // Set a variable if the event has passed.
    $current_time = \Drupal::time()->getCurrentTime();
    $event_datetime = new DrupalDateTime($variables['node']->get('field_event_datetime')->start_date);
    $variables['event_start_date'] = $event_datetime;

    if ($current_time > $event_datetime->getTimestamp()) {
      $variables['event_in_progress'] = TRUE;
    }
  }

  if (!empty($node->book)) {
    $book = $node->book;
    $pid = $book['pid'];

    if ($pid && $pid != -1) {
      $parent_node = Node::load($pid);
      if ($parent_node) {
        $parent_title = $parent_node->getTitle();
        $variables['book_parent_title'] = $parent_title;
        $variables['book_parent_url'] = '/node/' . strval($pid);
      }
    }
  }
}

/**
 * Implements hook_preprocess_block().
 */
function nicsdru_dept_theme_preprocess_block(array &$variables) {

  // Set department logo for system branding block.
  if ($variables['plugin_id'] === 'system_branding_block') {
    $dept = \Drupal::service('department.manager')->getCurrentDepartment();

    if (is_object($dept) && method_exists($dept, 'id')) {
      $variables["department_logo"] = 'logo-' . $dept->id() . '.svg';
      if (method_exists($dept, 'name')) {
        $variables['site_name'] = $dept->name();
        // Introduce a custom preprocess variable
        // as <front> is often hijacked to always show
        // the domain_source homepage and that doesn't
        // work for viewing this element on other sites.
        $variables['department_url'] = $dept->url();
      }
    }
    else {
      $variables["department_logo"] = 'logo-admin.svg';
      $variables['site_name'] = t('DEPT ADMIN');
    }
  }

  // Add nav-menu class Related Content and Conditions blocks.
  if ($variables['plugin_id'] === 'dept_related_content_block') {
    $variables['content']['related_content']['#attributes']['class'] = ['nav-menu'];
  }
}

/**
 * Implements hook_preprocess_page_title().
 */
function nicsdru_dept_theme_preprocess_page_title(&$variables) {
  // Preprocess titles on certain routes.
  $route = \Drupal::routeMatch();

  // Will need bits and pieces from the request for titles.
  $request = \Drupal::request();

  $variables['title_text_visible'] = TRUE;

  // Set the home page title and visually hide it.
  if (\Drupal::service('path.matcher')->isFrontPage()) {
    $dept = \Drupal::service('department.manager')->getCurrentDepartment();
    if (is_object($dept) && method_exists($dept, 'name')) {
      $variables['title'] = t('Welcome to ') . $dept->name();
    }

    $variables['title_text_visible'] = FALSE;
  }

  switch ($route->getRouteName()) {

    // Preprocess search page title.
    case "view.search.search_page":
      // Modify title if a search query is present.
      $query = $request->get('query');
      if (!empty($query)) {
        $variables['title'] = t('Search results');
      }
      // Add a subtitle to indicate the current page selected from the pager.
      $page = $request->get('page');
      if (!empty($page) && $page > 0) {
        $page = ++$page;
        $variables['title_subtitle'] = t('page :page', [':page' => $page]);
      }
      break;

    // Preprocess news listing page title.
    case "dept_news.news_listing":
      // Add a subtitle to indicate the current page selected from the pager.
      $page = $request->get('page');
      if (!empty($page) && $page > 0) {
        $page = ++$page;
        $variables['title_subtitle'] = t('page :page', [':page' => $page]);
      }
      break;

  }
}

/**
 * Implements hook_preprocess_field().
 */
function nicsdru_dept_theme_preprocess_field(&$variables) {
  // Add a class to the anchor tag on application link fields & append the icon.
  if (empty($variables['element'])) {
    return;
  }

  $field_name = $variables['element']['#field_name'] ?? '';
  $element_bundle = $variables['element']['#bundle'] ?? '';
  $field_type = $variables['element']['#field_type'] ?? '';

  if ($field_name === 'field_link' && $element_bundle === 'application') {
    foreach ($variables['items'] as $key => $item) {
      $icon = '<svg aria-hidden="true" class="ico ico-arrow-right"><use xlink:href="#arrow"></use></svg>';
      $title = $item['content']['#title'];
      $classes = ['call-to-action'];

      $formattedTitle = Markup::create($title . $icon);

      $variables['items'][0]['content']['#title'] = $formattedTitle;
      $variables['items'][0]['content']['#url']->setOption('attributes', ['class' => $classes]);
    }
  }

  // Amend classes on the standard address field output.
  if ($field_type === 'address') {
    $item_count = count($variables['items']);
    for ($i = 0; $i < $item_count; $i++) {
      $variables['items'][$i]["content"]["#prefix"] = '<p class="adr" translate="no">';
      if (($key = array_search('address-line1', $variables['items'][$i]['content']['address_line1']['#attributes']['class'])) !== FALSE) {
        unset($variables['items'][$i]['content']['address_line1']['#attributes']['class'][$key]);
      }
      $variables['items'][$i]['content']['address_line1']['#attributes']['class'][] = 'street-address';
      if (($key = array_search('address-line2', $variables['items'][$i]['content']['address_line2']['#attributes']['class'])) !== FALSE) {
        unset($variables['items'][$i]['content']['address_line2']['#attributes']['class'][$key]);
      }
      $variables['items'][$i]['content']['address_line2']['#attributes']['class'][] = 'street-address';
      $i++;
    }
  }

  if ($field_name === 'body' && $element_bundle === 'webform') {
    $variables['body_summary'] = $variables['element']['#object']->body->summary;
  }
}

/**
 * Implements hook_preprocess_views_view().
 */
function nicsdru_dept_theme_preprocess_views_view(&$variables) {
  // Amend the role / labelling for search results pages.
  if ($variables['id'] === 'publications' && $variables['display_id'] === 'search_page') {
    $search_classes = [
      'card-deck',
      'border-bottom',
      'search-results',
    ];
    if (!isset($variables['attributes']['class'])) {
      $variables['attributes']['class'] = [];
    }
    $variables['attributes']['class'] = array_merge($variables['attributes']['class'], $search_classes);
    $variables['attributes']['role'] = 'region';
    $variables['attributes']['aria-label'] = t('search results');
  }

  if ($variables['id'] === 'popular_content' && $variables['display_id'] === 'pop_by_term') {
    $variables['attributes']['class'][] = 'sub-menu';
    $variables['attributes']['class'][] = 'sub-menu--popular';
  }
}

/**
 * Implements hook_preprocess_form().
 */
function nicsdru_dept_theme_preprocess_form(array &$variables) {
  $view = $variables['element']['#id'];
  switch ($view) {
    case "views-exposed-form-search-site-search":
    case "views-exposed-form-contacts-a-z-contacts-by-letter":
    case "views-exposed-form-contacts-contact-search":
    case "views-exposed-form-publications-search-publications-search":
    case "views-exposed-form-consultations-search-consultations-search":
    case "views-exposed-form-news-search-news-search":
    case "views-exposed-form-news-search-press-release-search":
    case "views-exposed-form-events-events-search":
      $variables['attributes']['class'][] = Html::cleanCssIdentifier('search-form');
      $variables['attributes']['class'][] = Html::cleanCssIdentifier('search-form--page');
      break;
  }
}

/**
 * Implements hook_preprocess_responsive_image_formatter().
 */
function nicsdru_dept_theme_preprocess_responsive_image_formatter(array &$variables) {
  // Load the parent media entity that the responsive image is
  // or might be associated to.
  $media_image = $variables['item']->getEntity();

  if ($media_image instanceof MediaInterface === FALSE) {
    return;
  }

  // If we've got a parent media entity and caption field value
  // then inject the value into the responsive image attributes collection.
  // We're relying on Drupal input sanitising from the media form as
  // well as safe Twig rendering in responsive-image.html.twig to cover
  // any content that is migrated in from the legacy db and could
  // potentially contain dodgy html.
  $caption = $media_image->get('field_caption')->value;

  if (!empty($caption)) {
    if ($variables['responsive_image_style_id'] != 'featured_news_thumbnail') {
      $variables['responsive_image']['#attributes']['caption'] = $caption;
    }
  }
}

/**
 * Implements template_preprocess_container().
 */
function nicsdru_dept_theme_preprocess_container(&$variables) {
  // Fix for HTML validation error caused by duplicate "edit-actions" ID present
  // in header search form and in the page feedback form.
  // @see https://www.drupal.org/project/drupal/issues/1852090.
  if (isset($variables['element']['#id'])) {
    $id = $variables['element']['#id'];
    if (in_array($id, ['edit-actions'])) {
      $id = Html::getUniqueId($id);
    }
    $variables['element']['#id'] = $id;
    $variables['attributes']['id'] = $id;
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function nicsdru_dept_theme_preprocess_views_view_rss(&$variables) {
  // Based on https://jungleran.com/node/44 for preprocess
  // code to refine the output of RSS feeds.
  $view = $variables['view'];

  // The 'link' in the preprocess variables doesn't give us the full path so
  // we can create a new one here to give us what we need.
  $display = $view->getDisplay();
  $variables['feed_url'] = $display->getUrl()->setAbsolute()->toString();

  // Alter namespaces to include the atom namespace as recommended
  // by the W3C validator service.
  $style = $view->getStyle();
  $style->namespaces = array_filter($style->namespaces, function ($key) {
    return $key === 'xmlns:dc';
  }, ARRAY_FILTER_USE_KEY);

  $style->namespaces['xmlns:atom'] = 'http://www.w3.org/2005/Atom';
  $variables['namespaces'] = new Attribute($style->namespaces);

  // Make dept name available for feed title and description.
  $dept = \Drupal::service('department.manager')->getCurrentDepartment();
  $variables['site_name'] = t("Northern Ireland Executive");
  if (method_exists($dept, 'name')) {
    $variables['site_name'] = $dept->name();
  }
}

/**
 * Implements hook_preprocess_region().
 */
function nicsdru_dept_theme_preprocess_region(&$variables) {
  // Make the title field text available in the related region.
  if ($variables['region'] == 'related') {
    if ($node = \Drupal::routeMatch()->getParameter('node')) {
      if ($node->getType() == 'topic' || $node->getType() == 'subtopic') {
        $variables['topic_title'] = $node->get('title')->view();
      }
    }
  }
}
