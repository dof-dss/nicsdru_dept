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

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create all relationships'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'article')
      ->sort('created', 'ASC')
      ->execute();

    $bundle = 'article';

    $migration_table = 'migrate_map_node_' . $bundle;

    $query = \Drupal::database()->select($migration_table, 'mt');
    $query->addField('mt', 'sourceid3', 'domains');
    $query->addField('mt', 'destid1', 'nid');
    $result = $query->execute();

    $rows = $result->fetchAllAssoc('nid');

    $etgrm_service = \Drupal::service('etgrm.manager');

    $batch_builder = new BatchBuilder();
    $total_nodes = count($rows);
    $batchId = 1;

    $batch_builder
      ->setTitle($this->t('Processing group relationships for @num nodes', ['@num' => $total_nodes,]))
      ->setFile(\Drupal::service('extension.list.module')->getPath('dept_etgrm') . '/etgrm.batch.inc')
      ->setFinishCallback('processGroupNodesFinished')
      ->setErrorMessage(t('Batch has encountered an error'));

    foreach ($rows as $row) {
      $group_ids = [];
      foreach (explode('-', $row->domains) as $domain) {
        $group_ids[] = EntityToGroupRelationshipManagerService::domainIDtoGroupId($domain);
      }

      $batch_builder->addOperation('processGroupNodes', [
        $batchId,
        $row->nid,
        $group_ids,
        $etgrm_service,
      ]);
      $batchId++;
      $total_nodes++;
    }

    batch_set($batch_builder->toArray());
  }

}
