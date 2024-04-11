<?php

namespace Drupal\dept_sitemap\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\domain\DomainNegotiator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Departmental sites: Sitemaps routes.
 */
class SitemapController extends ControllerBase {

  /**
   * @var \Drupal\domain\DomainNegotiator
   */
  protected $domainNegotiator;

  /**
   * {@inheritdoc}
   */
  public function __construct(DomainNegotiator $domain_negotiator) {
    $this->domainNegotiator = $domain_negotiator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('domain.negotiator')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    // Redirect the /sitemap.xml request to the variant url for the
    // current domain.
    $domain_id = $this->domainNegotiator->getActiveDomain()->id();
    return $this->redirect('simple_sitemap.sitemap_variant', ['variant' => $domain_id]);
  }

}
