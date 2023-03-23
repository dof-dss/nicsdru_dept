<?php

namespace Drupal\dept_sitemap\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
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
    $rows = [];

    if (array_key_exists('dept_admin', $dept_domains)) {
      unset($dept_domains['dept_admin']);
    }

    $active_sitemaps = $this->entityTypeManager()->getStorage('simple_sitemap')->loadByProperties(['status' => 'true']);

    foreach ($dept_domains as $id => $domain) {
      $row = [
        'department' => $domain->label(),
        'sitemap' => $this->t('No'),
        'link_total' => '',
        'status' => '',
      ];

      $links = [];

      if (array_key_exists($domain->id(), $active_sitemaps)) {
        $row['sitemap'] = $this->t('Yes');
        $row['link_total'] = $active_sitemaps[$id]->getLinkCount();
        $row['status'] = $this->t('Pending');


        /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $entity */
        if ($active_sitemaps[$id]->fromPublishedAndUnpublished()->getChunkCount()) {
          switch ($active_sitemaps[$id]->contentStatus()) {

            case SimpleSitemap::SITEMAP_UNPUBLISHED:
              $row['status'] = $this->t('Generating');
              break;

            case SimpleSitemap::SITEMAP_PUBLISHED:
            case SimpleSitemap::SITEMAP_PUBLISHED_GENERATING:
              $created = \Drupal::service('date.formatter')->format($active_sitemaps[$id]->fromPublished()->getCreated());
            $row['status'] = $active_sitemaps[$id]->contentStatus() === SimpleSitemap::SITEMAP_PUBLISHED
                ? $this->t('Published on @time', ['@time' => $created])
                : $this->t('Published on @time, regenerating', ['@time' => $created]);
              break;
          }
        }

        $links['view'] = [
          'title' => t('View'),
          'url' => Url::fromRoute('simple_sitemap.sitemap_variant', [
            'variant' => $id,
          ]),
        ];
        $row['operations'] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];

      }
      else {
        $links['create'] = [
          'title' => t('Create'),
          'url' => Url::fromRoute('simple_sitemap.sitemap_variant', [
            'variant' => $id,
          ]),
        ];
        $row['operations'] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
      }

      $rows[] = $row;
    }

    $header = [
      $this->t('Department'),
      $this->t('Sitemap'),
      $this->t('Link count'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $build['content'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];


    return $build;
  }

}
