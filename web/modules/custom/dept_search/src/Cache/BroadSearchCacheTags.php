<?php

namespace Drupal\dept_search\Cache;

/**
 * Identifies broad Search API cache tags used by public search listings.
 */
final class BroadSearchCacheTags {

  /**
   * Search API tag prefixes that cause whole-index public listing purges.
   */
  public const PREFIXES = [
    'search_api_list:',
    'search_api_autocomplete_search_list:views:',
  ];

  /**
   * Removes broad Search API tags from a cache tag list.
   *
   * @param string[] $tags
   *   Cache tags to filter.
   *
   * @return string[]
   *   Cache tags that do not match the broad Search API prefixes.
   */
  public static function removeBroadTags(array $tags): array {
    return array_values(array_diff($tags, self::findBroadTags($tags)));
  }

  /**
   * Finds broad Search API tags that should not bubble to public pages.
   *
   * @param string[] $tags
   *   Cache tags to inspect.
   *
   * @return string[]
   *   Broad Search API tags.
   */
  public static function findBroadTags(array $tags): array {
    return array_values(array_filter($tags, static function (string $tag): bool {
      foreach (self::PREFIXES as $prefix) {
        if (str_starts_with($tag, $prefix)) {
          return TRUE;
        }
      }

      return FALSE;
    }));
  }

}
