<?php

namespace Drupal\dept_sitemap\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Departmental sites: Sitemaps routes.
 */
class SitemapAdminController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
    );
  }

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
        'sitemap' => $this->t(''),
        'link_total' => '',
        'status' => '',
      ];

      $links = [];

      if (array_key_exists($domain->id(), $active_sitemaps)) {
        /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $sitemap */
        $sitemap = $active_sitemaps[$id];
        $row['sitemap'] = $sitemap->getType()->label();
        $row['link_total'] = $sitemap->getLinkCount();
        $row['status'] = $this->t('Pending');

        if ($sitemap->fromPublishedAndUnpublished()->getChunkCount()) {
          switch ($sitemap->contentStatus()) {

            case SimpleSitemap::SITEMAP_UNPUBLISHED:
              $row['status'] = $this->t('Generating');
              break;

            case SimpleSitemap::SITEMAP_PUBLISHED:
            case SimpleSitemap::SITEMAP_PUBLISHED_GENERATING:
              $created = $this->dateFormatter->format($sitemap->fromPublished()->getCreated());
              $row['status'] = $sitemap->contentStatus() === SimpleSitemap::SITEMAP_PUBLISHED
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
        $links['add'] = [
          'title' => t('Add'),
          'url' => Url::fromRoute('dept_sitemap.add', [
            'department' => $id,
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

  /**
   * Create a sitemap for a given Department.
   *
   * @param string $department
   *   The department id.
   * @return array
   *   The response render array.
   */
  public function add($department) {

    /** @var \Drupal\simple_sitemap\Entity\SimpleSitemapInterface $simple_sitemap */
    $simple_sitemap = $this->entityTypeManager()->getStorage('simple_sitemap')->create();
    $simple_sitemap->set('id', $department);
    $simple_sitemap->set('label', ucfirst($department));
    $simple_sitemap->set('type', 'default_hreflang');
    $result = $simple_sitemap->save();

    if ($result > 0) {
      $build = [];

      $build['intro'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Sitemap sucessfully created for @department.', ['@department' => $department])
      ];

      $build['inclusions_text'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t("A sitemap will not be generated for this department until the 'Inclusions' configuration is set.")
      ];

      $build['inclusions_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Assign Inclusions for this sitemap'),
        '#url' => Url::fromRoute('simple_sitemap.entities')
      ];

      $build['sitemaps_link'] = [
        '#type' => 'link',
        '#title' => $this->t('View list of department sitemaps'),
        '#url' => Url::fromRoute('dept_sitemap.list'),
        '#prefix' => ' or ',
      ];

      return $build;

    }
    else {
      return [
        '#markup' => $this->t('There was an issue creating a sitemap for @department', ['@department' => $department])
      ];
    }

  }

}
