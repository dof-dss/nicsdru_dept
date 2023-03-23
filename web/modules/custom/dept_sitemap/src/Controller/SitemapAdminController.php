<?php

namespace Drupal\dept_sitemap\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\simple_sitemap\Entity\SimpleSitemap;

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

    foreach ($dept_domains as $id => $domain) {
      if (array_key_exists($domain->id(), $active_sitemaps)) {
        $rows[$id] = ['title' => $domain->label(), 'sitemap' => $this->t('Yes'), 'links' => $active_sitemaps[$id]->getLinkCount(), 'status' => $this->t('Pending')];

        /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity */
        if ($active_sitemaps[$id]->fromPublishedAndUnpublished()->getChunkCount()) {
          switch ($active_sitemaps[$id]->contentStatus()) {

            case SimpleSitemap::SITEMAP_UNPUBLISHED:
              $rows[$id]['status'] = $this->t('Generating');
              break;

            case SimpleSitemap::SITEMAP_PUBLISHED:
            case SimpleSitemap::SITEMAP_PUBLISHED_GENERATING:
              $created = \Drupal::service('date.formatter')->format($active_sitemaps[$id]->fromPublished()->getCreated());
              $rows[$id]['status'] = $active_sitemaps[$id]->contentStatus() === SimpleSitemap::SITEMAP_PUBLISHED
                ? $this->t('Published on @time', ['@time' => $created])
                : $this->t('Published on @time, regenerating', ['@time' => $created]);
              break;
          }
        }

      } else {
        $rows[$id] = ['department' => $domain->label(), 'sitemap' => $this->t('No'), 'links' => 0, 'status' => ''];
      }
    }

    $header = [
      $this->t('Department'),
      $this->t('Sitemap'),
      $this->t('Link count'),
      $this->t('Status'),
    ];

    $build['content'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];


    return $build;
  }

}
