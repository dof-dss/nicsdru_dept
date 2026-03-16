<?php

namespace Drupal\dept_publications;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

class SecurePublicationsFileAccess {

  /**
   * @var string
   */
  protected $fileUri;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param string $file_uri
   *   The URI of a file.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user service object.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service object.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend object.
   */
  public function __construct(string $file_uri, AccountInterface $user, Connection $connection, EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache) {
    $this->fileUri = $file_uri;
    $this->user = $user;
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->cache = $cache;
  }

  /**
   * @param string $file_uri
   *   File uri, eg: private://directory/file.pdf.
   *
   * @return bool
   *   TRUE if can access, FALSE if not.
   */
  public function canAccessSecureFileAttachment(string $file_uri) {
    $sql = "SELECT DISTINCT
      fm.uri,
      fm.fid,
      fm.filename,
      mfd.mid,
      mfd.name,
      nfd.nid,
      nfd.type,
      nfd.title
      FROM file_managed fm
      JOIN file_usage fu ON fu.fid = fm.fid
      JOIN media_field_data mfd ON mfd.mid = fu.id
      JOIN node__field_publication_secure_files nfpsf ON nfpsf.field_publication_secure_files_target_id = mfd.mid
      JOIN node_field_data nfd ON nfd.nid = nfpsf.entity_id
      WHERE nfd.type = 'publication'
      AND fm.uri = :file_uri";

    $results = $this->connection->query($sql, [':file_uri' => $file_uri])->fetchAssoc();

    if (empty($results)) {
      return FALSE;
    }

    // See if this is a published publication node.
    $nid = $results['nid'];

    /* @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    if ($node->isPublished() && $node->bundle() === 'publication') {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Check if a file URI is associated with a secure publication.
   *
   * @param string $uri
   *   The file URI to check.
   *
   * @return bool
   *   TRUE if the file is associated with a secure publication, FALSE otherwise.
   */
  public function isSecurePublicationFile(string $uri): bool {

    if (!str_starts_with($uri, 'private://')) {
      return FALSE;
    }

    static $static = [];

    if (isset($static[$uri])) {
      return $static[$uri];
    }

    $cid = 'secure_pub_file:' . hash('sha256', $uri);

    if ($cache = $this->cache->get($cid)) {
      return $static[$uri] = (bool) $cache->data;
    }

    $query = \Drupal::database()->select('file_managed', 'f');
    $query->join('media__field_media_file_1', 'm', 'm.field_media_file_1_target_id = f.fid');
    $query->condition('f.uri', $uri);
    $query->range(0, 1);

    $exists = (bool) $query->countQuery()->execute()->fetchField();

    $this->cache->set($cid, $exists);

    return $static[$uri] = $exists;
  }

}
