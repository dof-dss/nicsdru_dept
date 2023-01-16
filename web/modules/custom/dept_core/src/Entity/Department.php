<?php

namespace Drupal\dept_core\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\dept_core\DepartmentInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the department entity class.
 *
 * @ContentEntityType(
 *   id = "department",
 *   label = @Translation("Department"),
 *   label_collection = @Translation("Departments"),
 *   label_singular = @Translation("department"),
 *   label_plural = @Translation("departments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count departments",
 *     plural = "@count departments",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\dept_core\DepartmentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\dept_core\Form\DepartmentForm",
 *       "edit" = "Drupal\dept_core\Form\DepartmentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "department",
 *   fieldable = TRUE,
 *   revision_table = "department_revision",
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer department",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/content/department",
 *     "add-form" = "/department/add",
 *     "canonical" = "/department/{department}",
 *     "edit-form" = "/department/{department}/edit",
 *     "delete-form" = "/department/{department}/delete",
 *   },
 *   field_ui_base_route = "entity.department.settings",
 * )
 */
class Department extends RevisionableContentEntityBase implements DepartmentInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Domain for the department.
   *
   * @var array
   */
  protected array $domain;

  /**
   * Hostnames for the department.
   *
   * @var array
   */
  protected array $hostnames;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($update) {
      \Drupal::service('cache_tags.invalidator')->invalidateTags(['rendered', 'url.site']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the test entity.'))
      ->setReadOnly(TRUE)
      // Set to InnoDB 191 character limit.
      ->setSetting('max_length', 191);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setRevisionable(TRUE)
      ->setLabel(t('Description'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the department was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the department was last edited.'));

    return $fields;
  }

  /**
   * Department name.
   */
  public function name() {
    return $this->label();
  }


  /**
   * Domain.
   */
  public function domain() {
    // Cannot inject services into entities (https://www.drupal.org/project/drupal/issues/2142515)
    // So instead we lazy load the hostnames via the static Drupal calls.
    if (empty($this->domain)) {
      $domain = \Drupal::entityTypeManager()->getStorage('domain')->load($this->id());
    }

    return $domain;
  }

  /**
   * Full URL (protocol and hostname).
   *
   * @param bool $live_url
   *   Return live URL if true, else return the configuration Url.
   * @param bool $secure_protocol
   *   Return URL with HTTPS or HTTP protocol.
   */
  public function url(bool $live_url = TRUE, bool $secure_protocol = TRUE): string {
    return ($secure_protocol ? "https://" : "http://") . $this->hostname($live_url);
  }

  /**
   * Hostname.
   *
   * @param bool $production_hostname
   *   Return production hostname if true, else return the configuration hostname.
   */
  public function hostname(bool $production_hostname = TRUE): string|null {
    // Cannot inject services into entities (https://www.drupal.org/project/drupal/issues/2142515)
    // So instead we lazy load the hostnames via the static Drupal calls.
    if (empty($this->hostnames)) {
      // Get Production and config domain hostnames.
      foreach (['config.storage.sync', 'config.storage'] as $config_store) {
        $config = \Drupal::service($config_store)->read('domain.record.' . $this->id());
        $this->hostnames[] = $config['hostname'];
      }
    }

    return $production_hostname ? $this->hostnames[0] : $this->hostnames[1];
  }
}
