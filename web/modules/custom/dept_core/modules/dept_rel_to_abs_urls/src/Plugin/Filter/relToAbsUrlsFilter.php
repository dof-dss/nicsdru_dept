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

  // TODO: Add an admin option to restrict this by domain.

  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);


    $updated_text = preg_replace_callback(
      '/data-entity-uuid="(.+)" href="(\/\S+)"/m',
      static function ($matches) {
        // TODO: Inject
        $node  = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $matches[1]]);
        $node = reset($node);
        // TODO: Check node object has getGroups method or is of a group content type plugin.
        $groups = $node->getGroups();

        if (!empty($groups)) {
          $group_id = array_key_first($groups);

          // TODO: Inject service.
          /* @var $dept_man \Drupal\dept_core\DepartmentManager */
          $dept_man = \Drupal::service('department.manager');

          /* @var $dept \Drupal\dept_core\Department*/
          $dept = $dept_man->getDepartment('group_' . $group_id);
          $hostname = $dept->hostname();

          return 'href="https://' . $hostname . $matches[2] . '"';
        }


      },
      $result
    );

    if ($updated_text) {
      $result = new FilterProcessResult($updated_text);
    }

    return $result;
  }

}
