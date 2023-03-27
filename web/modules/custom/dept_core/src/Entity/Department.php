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
 *     "weight" = "weight",
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

    // Maybe a bit heavy-handed, but I can't see Departments getting updated
    // much and this reduces the need for adding lots of 'dept:xyz' type cache
    // tags to render arrays.
    if ($update) {
      \Drupal::service('cache_tags.invalidator')->invalidateTags([
        'rendered',
        'url.site'
      ]);
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

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('The weight/order of this Department.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'number_integer',
        'weight' => 25,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 50,
      ]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function name() {
    return $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function domain() {
    // Cannot inject services into entities (https://www.drupal.org/project/drupal/issues/2142515)
    // So instead we lazy load the hostnames via the static Drupal calls.
    if (empty($this->domain)) {
      $this->domain = \Drupal::entityTypeManager()->getStorage('domain')->load($this->id());
    }

    return $this->domain;
  }

  /**
   * Full URL (protocol and hostname).
   *
   * @param string $environment
   *   Environment to return the URL for.
   * @param bool $secure_protocol
   *   Return URL with HTTPS or HTTP protocol.
   */
  public function url(string $environment = 'active', bool $secure_protocol = TRUE): string {
    return ($secure_protocol ? "https://" : "http://") . $this->hostname($environment);
  }

  /**
   * Hostname.
   *
   * @param string $environment
   *   Return hostname for the given environment.
   */
  public function hostname(string $environment = "active"): string|null {
    $active_split = '';

    if (empty($this->hostnames)) {
      /** @var \Drupal\config_split\ConfigSplitManager $split_manager */
      $split_manager = \Drupal::service('config_split.manager');
      /** @var \Drupal\config_filter\ConfigFilterStorageFactory $conf_filter */
      $conf_filter = \Drupal::service('config_filter.storage_factory');
      $split_ids = $split_manager->listAll();

      foreach ($split_ids as $split_id) {
        /** @var \Drupal\Core\Config\ImmutableConfig $split_config */
        $split_config = $split_manager->getSplitConfig($split_id);
        $storage = $split_manager->singleExportTarget($split_config);

        /** @var \Drupal\config_filter\Config\FilteredStorageInterface $config_store */
        $config_store = $conf_filter->getFilteredStorage($storage, ['config.storage']);

        $config = $config_store->read('domain.record.' . $this->id());
        $this->hostnames[substr($split_id, 26)] = $config['hostname'];

        $active_split = $split_config->get('status') ? $split_id : $active_split;
      }
    }

    $environment = ($environment == 'active') ? substr($active_split, 26) : $environment;

    return array_key_exists($environment, $this->hostnames) ? $this->hostnames[$environment] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->get('weight')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * Management and Structure details.
   */
  public function managementAndStructure() {
    return $this->get('field_dept_management_structure')->view();
  }

  /**
   * Access to information details.
   */
  public function accessToInformation() {
    return $this->get('field_dept_access_to_info')->view();
  }

  /**
   * Contact Information details.
   */
  public function contactInformation() {
    return $this->get('field_dept_contact_info')->view();
  }

  /**
   * Social media links.
   */
  public function socialMediaLinks() {
    return $this->get('field_dept_social_media_links')->view();
  }

  /**
   * Point of contact map location.
   */
  public function location() {
    return $this->get('field_dept_location')->view();
  }

  /**
   * Accessibility statement.
   */
  public function accessibilityStatement() {
    return (empty($this->get('field_dept_accessibility')->referencedEntities())) ? NULL : $this->get('field_dept_accessibility')->referencedEntities()[0];

  }

}
