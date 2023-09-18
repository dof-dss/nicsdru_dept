<?php

namespace Drupal\dept_user\Plugin\migrate\source\d7;

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

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['uuid']['type'] = 'string';
    return $ids;
  }

}
