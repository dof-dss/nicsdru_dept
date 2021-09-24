<?php

namespace Drupal\dept_migrate\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\user\Plugin\migrate\source\d7\User;

/**
 * Drupal 7 Domain access from database.
 *
 * @MigrateSource(
 *   id = "d7_user_domain",
 *   source_module = "user"
 * )
 */
class UserDomain extends User {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['domain_user'] = $this->t("User Domain");
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $row->setSourceProperty('domain_user', $this->getDomainTargetIds($row->getSourceProperty('uid')));

    return parent::prepareRow($row);
  }

  /**
   * Helper method to get the gids as target ids from d7 domain_editor.
   *
   * @param int $uid
   *   Uid of the current row.
   *
   * @return array
   *   returns target ids of domains
   */
  private function getDomainTargetIds(int $uid) {
    $row_source_properties = [];

    $domains = $this->select('domain_editor', 'de')
      ->fields('de', ['domain_id'])
      ->condition('de.uid', $uid)
      ->execute()
      ->fetchCol();

    foreach ($domains as $domain) {
      $domain_target_ids = $this->select('domain', 'da')
        ->fields('da', ['machine_name'])
        ->condition('da.domain_id', $domain)
        ->execute()
        ->fetchCol();
      $row_source_properties[] = ['target_id' => $domain_target_ids[0]];
    }
    return $row_source_properties;
  }

}
