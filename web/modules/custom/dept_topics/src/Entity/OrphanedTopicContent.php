<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\dept_topics\OrphanedTopicContentInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the orphaned topic content entity class.
 *
 * @ContentEntityType(
 *   id = "topics_orphaned_content",
 *   label = @Translation("Orphaned topic content"),
 *   label_collection = @Translation("Orphaned topic contents"),
 *   label_singular = @Translation("orphaned topic content"),
 *   label_plural = @Translation("orphaned topic contents"),
 *   label_count = @PluralTranslation(
 *     singular = "@count orphaned topic contents",
 *     plural = "@count orphaned topic contents",
 *   ),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "edit" = "Drupal\dept_topics\Form\OrphanedTopicContentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\dept_topics\Routing\OrphanedTopicContentHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "topics_orphaned_content",
 *   admin_permission = "administer topics_orphaned_content",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/admin/orphaned-content/{topics_orphaned_content}",
 *     "edit-form" = "/admin/orphaned-content/{topics_orphaned_content}",
 *     "delete-form" = "/admin/orphaned-content/{topics_orphaned_content}/delete",
 *     "delete-multiple-form" = "/admin/content/topics-orphaned-content/delete-multiple",
 *   },
 * )
 */
final class OrphanedTopicContent extends ContentEntityBase implements OrphanedTopicContentInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['orphan'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Orphan'))
      ->setRequired(TRUE)
      ->setDescription(t('Reference to the orphaned content.'))
      ->setSettings([
        'target_type' => 'node',
        'default_value' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'node',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['former_parent'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Former parent'))
      ->setDescription(t('Parent for which the orphan was previously assigned.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the orphaned topic content was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the orphaned topic content was last edited.'));

    return $fields;
  }

}
