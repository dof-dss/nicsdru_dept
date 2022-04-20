<?php

namespace Drupal\dept_etgrm\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dept_etgrm\EntityToGroupRelationshipManagerService;

/**
 * Provides a form to create all node to group relations.
 */
class CreateAllRelationshipsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dept_etgrm_create_all_relationships';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $departments =  \Drupal::entityTypeManager()->getStorage('group_type')->load('department_site');
    $etgrm = \Drupal::service('etgrm.manager');
    $group_bundles = [];

    foreach ($departments->getInstalledContentPlugins() as $plugin) {
      if ($plugin->getEntityTypeId() === 'node') {
        $group_bundles[$plugin->getEntityBundle()] = $plugin->getEntityBundle();
      }
    }

    $form['bundle'] = [
      '#type' => 'select',
      '#options' => $group_bundles,
      '#title' => $this->t("Bundle"),
    ];


    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create group relationships'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $bundle = $form_state->getValue('bundle');

    $etgrm_service = \Drupal::service('etgrm.manager');

    $migration_table = 'migrate_map_node_' . $bundle;

    $query = \Drupal::database()->select($migration_table, 'mt');
    $query->addField('mt', 'sourceid3', 'domains');
    $query->addField('mt', 'destid1', 'nid');
    $result = $query->execute();

    $rows = $result->fetchAllAssoc('nid');

    $batch_builder = new BatchBuilder();

    $batch_builder
      ->setTitle($this->t('Creating group relationships for @bundle', ['@bundle' => $bundle,]))
      ->setFile(\Drupal::service('extension.list.module')->getPath('dept_etgrm') . '/etgrm.batch.inc')
      ->setFinishCallback('buildGroupRelationshipsFinished')
      ->setErrorMessage(t('Batch has encountered an error'));

    foreach ($rows as $row) {
      $batch_builder->addOperation('buildGroupRelationships', [
        $row,
        $bundle,
        $etgrm_service,
      ]);
    }

    batch_set($batch_builder->toArray());
  }

}
