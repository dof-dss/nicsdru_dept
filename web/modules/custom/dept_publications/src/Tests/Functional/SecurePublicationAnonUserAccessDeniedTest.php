<?php

namespace Drupal\Tests\dept_publications\Functional;

use Drupal\Core\Config\FileStorage;
use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain\Entity\Domain;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\domain_source\DomainSourceElementManagerInterface;
use Drupal\media\Entity\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests that anonymous users can't see an unpublished secure publication node.
 *
 * NB: CLI debugging (dump) can be achieved as below, as phpunit
 * will swallow any non-assertion related output.
 *
 * dump($domain->id());
 * $this->assertSession()->assertTrue(TRUE);
 *
 * TODO: for now you also need to adjust the DepartmentManager
 * class to return a fixed dept entity. Likely fixable by passing in
 * a static object but as a quick local workaround this works too:
 * `return $this->getDepartment('nigov');`
 *
 * @group dept_publications
 */
class SecurePublicationAnonUserAccessDeniedTest extends DomainTestBase {

  /**
   * Set default theme.
   *
   * Drupal\Tests\BrowserTestBase::$defaultTheme is required. See
   * https://www.drupal.org/node/3083055, which includes recommendations
   * on which theme to use.
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $statsAuthorUser;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $statsSuperVisorUser;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $regularAuthorUser;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * @var \Drupal\domain\DomainInterface
   */
  protected $domain;

  /**
   * @var \Drupal\media\MediaInterface
   */
  protected $mediaDoc;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $unpublishedSecurePublication;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $publishedSecurePublication;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $unpublishedPublication;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $publishedPublication;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'dept_core',
    'dept_publications',
    'dept_user',
    'domain',
    'domain_access',
    'field',
    'media',
    'node',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $config_path = '../config/sync';
    $config_source = new FileStorage($config_path);
    \Drupal::service('config.installer')->installOptionalConfig($config_source);

    Domain::create([
      'hostname' => 'nigov.lndo.site',
      'name' => 'The Northern Ireland Assembly',
      'id' => 'nigov',
      'domain_id' => '11933791',
      'scheme' => 'variable',
      'is_default' => TRUE,
    ])->save();
    $this->domain = Domain::load('nigov');

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'bypass node access'
    ]);
    $this->regularAuthorUser = $this->drupalCreateUser([
      'access administration pages',
      'create publication content',
      'edit own publication content',
    ]);
    $this->statsAuthorUser = $this->drupalCreateUser([
      'access administration pages',
      'create publication content',
      'edit own publication content',
      'view own unpublished secure publication',
    ]);
    $this->statsSuperVisorUser = $this->drupalCreateUser([
      'access administration pages',
      'create publication content',
      'edit own publication content',
      'view any unpublished secure publication'
    ]);

    $mediaDoc = Media::create([
      'name' => 'A secure document media entity',
      'bundle' => 'document',
      'status' => 1,
    ]);
    $this->mediaDoc = $mediaDoc;

    $this->unpublishedSecurePublication = $this->drupalCreateNode([
      'title' => 'Unpublished SECURE publication node',
      'type' => 'publication',
      'status' => 0,
      'field_publication_secure_files' => [
        'target_id' => $this->mediaDoc->id(),
      ],
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => [$this->domain->id()],
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => [$this->domain->id()],
    ]);

    $this->publishedSecurePublication = $this->drupalCreateNode([
      'title' => 'Published SECURE publication node',
      'type' => 'publication',
      'status' => 1,
      'field_publication_secure_files' => [
        'target_id' => $this->mediaDoc->id(),
      ],
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => [$this->domain->id()],
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => [$this->domain->id()],
    ]);

    $this->publishedPublication = $this->drupalCreateNode([
      'title' => 'Published publication node',
      'type' => 'publication',
      'status' => 1,
      'field_publication_files' => [
        'target_id' => $this->mediaDoc->id(),
      ],
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => [$this->domain->id()],
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => [$this->domain->id()],
    ]);

    $this->unpublishedPublication = $this->drupalCreateNode([
      'title' => 'Unpublished publication node',
      'type' => 'publication',
      'status' => 0,
      'field_publication_files' => [
        'target_id' => $this->mediaDoc->id(),
      ],
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => [$this->domain->id()],
      DomainSourceElementManagerInterface::DOMAIN_SOURCE_FIELD => [$this->domain->id()],
    ]);
  }

  /** @test */
  public function anonymousUserCannotAccessUnpublishedSecurePublication() {
    $securePubNode = $this->unpublishedSecurePublication;
    $path = $this->domain->getPath() . 'node/' . $securePubNode->id();
    $this->drupalGet($path);

    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
  }

  /** @test */
  public function editorUserCannotEditPublishedSecurePublication() {
    $this->drupalLogin($this->regularAuthorUser);

    $securePubNode = $this->publishedSecurePublication;
    $path = $this->domain->getPath() . 'node/' . $securePubNode->id() . '/edit';
    $this->drupalGet($path);

    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
  }

  /** @test */
  public function statsUserAuthorCannotEditSomeoneElsesSecurePublication() {
    $this->drupalLogin($this->statsAuthorUser);

    $secureUnpublishedPubNode = $this->unpublishedSecurePublication;
    $path = $this->domain->getPath() . 'node/' . $secureUnpublishedPubNode->id() . '/edit';
    $this->drupalGet($path);

    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
  }

  /** @test */
  public function statsUserAuthorCanEditOwnSecurePublication() {
    $this->drupalLogin($this->statsAuthorUser);

    $secureUnpublishedPubNode = $this->unpublishedSecurePublication;
    $secureUnpublishedPubNode->setOwnerId($this->statsAuthorUser->id());
    $secureUnpublishedPubNode->save();

    $path = $this->domain->getPath() . 'node/' . $secureUnpublishedPubNode->id() . '/edit';
    $this->drupalGet($path);

    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
  }

}
