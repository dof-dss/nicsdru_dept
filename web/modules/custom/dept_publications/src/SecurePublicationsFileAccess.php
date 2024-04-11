<?php

namespace Drupal\dept_publications;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

class SecurePublicationsFileAccess {

  /**
   * The File Uri the check.
   * @var string
   */
  protected $fileUri;

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
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service object.
   */
  public function __construct(string $file_uri, Connection $connection, EntityTypeManagerInterface $entity_type_manager) {
    $this->fileUri = $file_uri;
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Determines if a secure file can be accessed/downloaded.
   *
   * @param string $file_uri
   *   File uri, eg: private://directory/file.pdf.
   *
   * @return bool
   *   TRUE if you can access, FALSE if not.
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

}
