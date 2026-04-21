<?php
namespace Drupal\dept_fs\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Class DupFileLink
 *
 * @ViewsField("dup_file_link")
 */
class DupFileLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $dupfile = $this->getValue($values);
    if ($dupfile) {
      return [
        '#type' => 'link',
        '#title' => $dupfile,
        '#url' => Url::fromUri('base:' . substr($dupfile, 9), ['attributes' => ['target' => '_blank']]),
      ];
    }
  }
}
