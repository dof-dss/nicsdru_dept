<?php

namespace Drupal\dept_example_group_entity\Plugin\GroupContentEnabler;

use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a content enabler for dept example group entity.
 *
 * @GroupContentEnabler(
 *   id = "dept_example_group_entity_enabler",
 *   label = @Translation("Dept example Group entity"),
 *   description = @Translation("Adds dept example group entity"),
 *   entity_type_id = "dept_example_group_entity",
 *   entity_access = TRUE,
 *   pretty_path_key = "node",
 *   reference_label = @Translation("Title"),
 *   reference_description = @Translation("The name of the dept example group entity to add to the group"),
 *   deriver = "Drupal\dept_example_group_entity\Plugin\GroupContentEnabler\DeptExampleGroupEntityDeriver",
 *   handlers = {
 *     "access" = "Drupal\group\Plugin\GroupContentAccessControlHandler",
 *     "permission_provider" = "Drupal\dept_example_group_entity\Plugin\DeptExampleGroupEntityPermissionProvider",
 *   }
 * )
 */
class DeptExampleGroupEntityGroupEnabler extends GroupContentEnablerBase {

  /**
   * {@inheritdoc}
   */
  public function getGroupOperations(GroupInterface $group) {
    $plugin_id = $this->getPluginId();
    $group_operations = [];

    if ($group->hasPermission("create $plugin_id entity", \Drupal::currentUser())) {
      $route_params = ['group' => $group->id(), 'plugin_id' => $plugin_id];
      $group_operations['advertiser-create-' . $this->getEntityBundle()] = [
        'title' => $this->t('Create @type', ['@type' => 'Dept example Group entity']),
        'url' => new Url('entity.group_content.create_form', $route_params),
        'weight' => 50,
      ];
    }

    return $group_operations;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();

    // Set the default value for entity_cardinality, most entities will be set
    // to a cardinality of 1.
    $config['entity_cardinality'] = 1;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Disable the entity cardinality field as the functionality of this module
    // relies on a cardinality of 1. We don't just hide it, though, to keep a UI
    // that's consistent with other content enabler plugins.
    $info = $this->t("This field has been disabled by the plugin to guarantee the functionality that's expected of it.");
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $dependencies['config'][] = 'node.type.' . $this->getEntityBundle();
    return $dependencies;
  }

}
