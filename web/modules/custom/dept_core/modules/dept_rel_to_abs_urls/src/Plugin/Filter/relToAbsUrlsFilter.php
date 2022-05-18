<?php

namespace Drupal\dept_rel_to_abs_urls\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * @Filter(
 *   id = "rel_to_abs_url",
 *   title = @Translation("Relative to Absolute URL Filter"),
 *   description = @Translation("Transform relative URLs to absolute URLs"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class relToAbsUrlsFilter extends FilterBase {

  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    $updated_text = preg_replace_callback(
      '/data-entity-uuid="(.+)" href="(\/\S+)"/m',
      static function ($matches) {
        // TODO: Replace with node group domain.
        return 'href="' . strtoupper($matches[2]) . '"';
      },
      $result
    );

    if ($updated_text) {
      $result = new FilterProcessResult($updated_text);
    }

    return $result;
  }

}
