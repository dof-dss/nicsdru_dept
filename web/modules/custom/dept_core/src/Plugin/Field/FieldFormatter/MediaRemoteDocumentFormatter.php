<?php

namespace Drupal\dept_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\media_remote\Plugin\Field\FieldFormatter\MediaRemoteFormatterBase;

/**
 * Plugin implementation of an 'oembed' formatter for remote document assets.
 *
 * @FieldFormatter(
 *   id = "media_remote_document",
 *   label = @Translation("Remote document"),
 *   field_types = {
 *     "string"
 *   },
 * )
 */
class MediaRemoteDocumentFormatter extends MediaRemoteFormatterBase {

  /**
   * @inheritDoc
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      if ($item->isEmpty()) {
        continue;
      }

      $item_url = $item->getValue()['value'] ?? '';

      if (empty($item_url) || preg_match($this::getUrlRegexPattern(), $item_url) === FALSE) {
        continue;
      }

      $elements[$delta] = Link::fromTextAndUrl($item_url, Url::fromUri($item_url))->toRenderable();
    }

    return $elements;
  }

  /**
   * @inheritDoc
   */
  public static function getUrlRegexPattern() {
    return '#^https:\/\/.+(.pdf|doc|docx|xls|xlsx|ppt|pptx|odt|ods|odp|csv|zip|html)$#';
  }

  /**
   * @inheritDoc
   */
  public static function getValidUrlExampleStrings(): array {
    return [
      'https://www.gov.uk/publications/some-publication.html',
      'https://www.gov.uk/publications/some-publication.pdf',
      'https://www.gov.uk/publications/some-publication.docx',
    ];
  }

}
