<?php

namespace Drupal\dept_migrate;

/**
 * Provides static utility methods for migrations.
 */
class MigrateUtils {

  /**
   * Converts a D7 Domain to the D9 counterpart.
   *
   * @param string $domain
   *   The Drupal 7 domain (machine name).
   *
   * @return string|null
   *   The Drupal 9 domain or Null if not matched.
   */
  public static function d7DomainToD9Domain(string $domain) {
    return match ($domain) {
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

  /**
   * Converts a D9 Domain to the D7 counterpart.
   *
   * @param string $domain
   *   The Drupal 9 domain (machine name).
   *
   * @return string|null
   *   The Drupal 7 domain or Null if not matched.
   */
  public static function d9DomainToD7Domain(string $domain) {
    return match ($domain) {
      'nigov' => 'newnigov',
      'daera' => 'daera',
      'economy' => 'economy',
      'executiveoffice' => 'execoffice',
      'education' => 'education',
      'finance' => 'dfp',
      'health' => 'health',
      'infrastructure' => 'infrastructure',
      'justice' => 'justice',
      'communities' => 'communities',
      default => NULL,
    };
  }

  /**
   * Returns array of departments that are active for migration.
   *
   * @return string[]
   *   Department ids.
   */
  public static function activeMigrationDepartments() {

    $departments = [
      'nigov',
      'daera',
      'economy',
      'executiveoffice',
      'education',
      'finance',
      'health',
      'infrastructure',
      'justice',
      'communities',
    ];

    $ignore_value = getenv('MIGRATE_IGNORE_SITES');
    $ignore_departments = explode(',', $ignore_value);

    return array_diff($departments, $ignore_departments);
  }

}
