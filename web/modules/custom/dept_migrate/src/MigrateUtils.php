<?php

namespace Drupal\dept_migrate;

/**
 * Provies static utility methods for migrations.
 */
class MigrateUtils {

  /**
   * Converts a D7 Domain id to the D9 counterpart.
   *
   * @param string $domain_id
   *   The Drupal 7 domain ID.
   *
   * @return string|null
   *   The Drupal 9 domain ID or Null if not matched.
   */
  public static function d7DomainToD9Domain(string $domain_id) {
    return match ($domain_id) {
      'newnigov' => 'nigov',
      'daera' => 'daera',
      'economy' => 'economy',
      'execoffice' => 'executiveoffice',
      'education' => 'education',
      'dfp' => 'finance',
      'health' => 'health',
      'infrastructure' => 'infrastructure',
      'justice' => 'justice',
      'communities' => 'communities',
      default => NULL,
    };
  }

}
