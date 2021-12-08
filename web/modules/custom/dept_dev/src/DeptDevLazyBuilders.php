<?php

namespace Drupal\dept_dev;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

/**
 * Dept Dev Lazy builders.
 */
class DeptDevLazyBuilders implements TrustedCallbackInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Dept Dev LazyBuilders Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['lazyLinks'];
  }

  /**
   * Lazy builder callback to build departmental site links.
   *
   * @return array
   *   A renderable array of site links.
   */
  public function lazyLinks() {
    $domains = $this->entityTypeManager->getStorage('domain')->loadMultiple();
    $lando_hostnames = \Drupal::config('dept_dev.settings')->get('toolbar_sites_lando_hostname');
    $links = [];

    foreach ($domains as $domain) {

      $url = $domain->getPath();
      if ($lando_hostnames) {
        $url = preg_replace('/https?:\/\/(www.)?(.*)(gov.uk)/', 'http://$2lndo.site', $url);
      }

      $links[$domain->id()] = [
        'title' => $domain->label(),
        'url' => Url::fromUri($url),
      ];
    }


    $configure_link = [
      '#type' => 'link',
      '#title' => t('Edit shortcuts'),
      '#url' => Url::fromRoute('domain.admin'),
      '#options' => ['attributes' => ['class' => ['edit-shortcuts']]],
      ];

    return [
      '#theme' => 'links__toolbar_sites',
      '#links' => $links,
      '#attributes' => [
        'class' => ['toolbar-menu'],
      ],
    ];
  }

}
