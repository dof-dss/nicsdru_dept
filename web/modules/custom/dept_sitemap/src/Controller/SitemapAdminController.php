<?php

namespace Drupal\dept_sitemap\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Departmental sites: Sitemaps routes.
 */
class SitemapAdminController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {


    $dept_domains = $this->entityTypeManager()->getStorage('domain')->loadByProperties(['status' => 'true']);

    if (array_key_exists('dept_admin', $dept_domains)) {
      unset($dept_domains['dept_admin']);
    }

    $active_sitemaps = $this->entityTypeManager()->getStorage('simple_sitemap')->loadByProperties(['status' => 'true']);

    foreach ($dept_domains as $domain) {
      if (array_key_exists($domain->id(), $active_sitemaps)) {
        $rows[] = [$domain->label(), 'true'];
      } else {
        $rows[] = [$domain->label(), 'false'];
      }
    }

    $header = [
      $this->t('Department'),
      $this->t('Sitemap'),
    ];

    $build['content'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];


    return $build;
  }

}
