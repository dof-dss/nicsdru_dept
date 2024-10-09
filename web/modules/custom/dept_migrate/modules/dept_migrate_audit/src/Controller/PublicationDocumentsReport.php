<?php

namespace Drupal\dept_migrate_audit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

class PublicationDocumentsReport extends ControllerBase {

  /**
   * @param \Drupal\Core\Database\Connection $dbConn
   *   The main db connection.
   * @param \Drupal\Core\Database\Connection $d7DbConn
   *   The d7 db connection.
   */
  public function __construct(protected Connection $dbConn, protected Connection $d7DbConn) {
    $this->d7DbConn = Database::getConnection('default', 'migrate');
  }

  /**
   * Callback to compare D7 and D10 publication result sets and
   * gather details of those which don't share the same number
   * of publication documents.
   *
   * @return array
   *   Result set of items that don't share the same doc count.
   */
  private function getZeroDocsPublicationReport() {
    $results = [];

    $d7_publications_with_doc_count = $this->getD7PublicationsWithDocCount();
    $d10_publications_with_doc_count = $this->getD10PublicationsWithDocCount();

    foreach ($d10_publications_with_doc_count as $d10p) {
      $d7p = $d7_publications_with_doc_count[$d10p->d7nid] ?? NULL;

      if (empty($d7p)) {
        continue;
      }

      $d7p_count = (int) $d7p->num_docs;
      $d10p_count = (int) ($d10p->num_doc_refs + $d10p->num_secure_doc_refs) ?? 0;

      if ($d7p_count != $d10p_count) {
        $results[$d10p->nid] = [
          $d10p->nid,
          $d10p->d7nid,
          $d10p->domain_source,
          $d10p->type,
          $d10p->title,
          $d10p->status,
          $d10p->source_row_status,
          $d10p_count,
          $d7p_count,
        ];
      }

    }

    return $results;
  }

  /**
   * Function to fetch D7 publications with doc count.
   *
   * @return array
   *   The result set of values.
   */
  private function getD7PublicationsWithDocCount() {
    $publications = $this->d7DbConn->query("select
      n.nid,
      n.status,
      d.machine_name,
      n.type,
      n.title,
      count(fdfa.field_attachment_fid) as num_docs
      from node n
      join domain_access da on da.nid = n.nid
      join domain d on d.domain_id = da.gid
      left join field_data_field_attachment fdfa on fdfa.entity_id = n.nid
      where
      n.type like '%publication%' and machine_name = 'dfp'
      group by
      n.nid")->fetchAllAssoc('nid');

    return $publications;
  }

  /**
   * Function to fetch D10 publications with doc count.
   *
   * @return array
   *   The result set of values.
   */
  private function getD10PublicationsWithDocCount() {
    $publications = $this->dbConn->query("select
      nfd.nid,
      nfd.status,
      nfds.field_domain_source_target_id as domain_source,
      nfd.type,
      nfd.title,
      mmnp.sourceid2 as d7nid,
      mmnp.source_row_status,
      count(nfpf.field_publication_files_target_id) as num_doc_refs,
      count(nfpsf.field_publication_secure_files_target_id) as num_secure_doc_refs
      from node_field_data nfd
      left join node__field_publication_files nfpf on nfpf.entity_id = nfd.nid
      left join node__field_publication_secure_files nfpsf on nfpsf.entity_id = nfd.nid
      join migrate_map_node_publication mmnp on mmnp.destid1 = nfd.nid
      join node__field_domain_source nfds on nfds.entity_id = nfd.nid
      where
      nfd.type = 'publication' and mmnp.source_row_status > 0
      group by nfd.nid, nfd.title
      having count(nfpf.field_publication_files_target_id) = 0 and count(nfpsf.field_publication_secure_files_target_id) = 0
    ")->fetchAll();

    return $publications;
  }

  /**
   * Constructor callback.
   *
   * @return array
   *   Render array for Drupal.
   */
  public function default() {
    $content = [];

    $results = $this->getZeroDocsPublicationReport();

    $header = [
      ['data' => $this->t('D10 Node ID')],
      ['data' => $this->t('D7 Node ID')],
      ['data' => $this->t('Domain source')],
      ['data' => $this->t('Type')],
      ['data' => $this->t('Title')],
      ['data' => $this->t('D10 Publish status')],
      ['data' => $this->t('Migration status')],
      ['data' => $this->t('D10 Document references')],
      ['data' => $this->t('D7 Document references')],
    ];

    $content[] = [
      '#markup' => $this->t('<h3>:numrows results. </h3>', [
        ':numrows' => count($results),
      ]),
    ];

    $content[] = [
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $results,
        '#empty' => $this->t('Nothing to display.'),
      ],
    ];

    return $content;
  }

}
