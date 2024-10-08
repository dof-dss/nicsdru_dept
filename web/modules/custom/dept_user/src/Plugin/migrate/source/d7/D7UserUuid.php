<?php

namespace Drupal\dept_user\Plugin\migrate\source\d7;

use Drupal\dept_migrate\MigrateUtils;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 user source from database, using UUID as PK.
 *
 * For available configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "d7_user_uuid",
 *   source_module = "user"
 * )
 */
class D7UserUuid extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('users', 'u')
      ->fields('u')
      ->condition('u.uid', 0, '>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'uid' => $this->t('User ID'),
      'name' => $this->t('Username'),
      'pass' => $this->t('Password'),
      'mail' => $this->t('Email address'),
      'signature' => $this->t('Signature'),
      'signature_format' => $this->t('Signature format'),
      'created' => $this->t('Registered timestamp'),
      'access' => $this->t('Last access timestamp'),
      'login' => $this->t('Last login timestamp'),
      'status' => $this->t('Status'),
      'timezone' => $this->t('Timezone'),
      'language' => $this->t('Language'),
      'init' => $this->t('Init'),
      'data' => $this->t('User data'),
      'roles' => $this->t('Roles'),
      'uuid' => $this->t('UUID'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $uid = $row->getSourceProperty('uid');

    $roles = $this->select('users_roles', 'ur')
      ->fields('ur', ['rid'])
      ->condition('ur.uid', $uid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('roles', $roles);

    // Fetch data first to prevent unserialize() warning with null values.
    $data = $row->getSourceProperty('data');

    if (!empty($data)) {
      $row->setSourceProperty('data', unserialize($row->getSourceProperty('data'), ['allowed_classes' => FALSE]));
    }

    // If this entity was translated using Entity Translation, we need to get
    // its source language to get the field values in the right language.
    // The translations will be migrated by the d7_user_entity_translation
    // migration.
    $entity_translatable = $this->isEntityTranslatable('user');
    $source_language = $this->getEntityTranslationSourceLanguage('user', $uid);
    $language = $entity_translatable && $source_language ? $source_language : $row->getSourceProperty('language');
    $row->setSourceProperty('entity_language', $language);

    // Get Field API field values.
    foreach ($this->getFields('user') as $field_name => $field) {
      // Ensure we're using the right language if the entity and the field are
      // translatable.
      $field_language = $entity_translatable && $field['translatable'] ? $language : NULL;
      $row->setSourceProperty($field_name, $this->getFieldValues('user', $field_name, $uid, NULL, $field_language));
    }

    $access = $row->getSourceProperty('access');
    // Block account if last accessed was 9 months or more.
    if (floor((time() - $access) / 2592000) >= 9) {
      $row->setSourceProperty('status', 0);
    }

    // Fetch domain assignments.
    $user_domains = $this->getDomainTargetIds($uid);
    $row->setSourceProperty('domain_access_user', $user_domains);
    // Assign canonical domain (use first result if multiple).
    $row->setSourceProperty('domain_source_user', $user_domains[0]);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['uuid']['type'] = 'string';
    return $ids;
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

    if (empty($domains)) {
      $row_source_properties[] = ['target_id' => 'nigov'];
    }
    else {
      foreach ($domains as $domain) {
        $domain_target_ids = $this->select('domain', 'da')
          ->fields('da', ['machine_name'])
          ->condition('da.domain_id', $domain)
          ->execute()
          ->fetchCol();
        $row_source_properties[] = ['target_id' => MigrateUtils::d7DomainToD9Domain($domain_target_ids[0])];
      }
    }

    return $row_source_properties;
  }

}
