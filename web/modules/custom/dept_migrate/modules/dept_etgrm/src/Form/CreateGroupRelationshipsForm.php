<?php

namespace Drupal\dept_etgrm\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dept_etgrm\EtgrmBatchService;

/**
 * Provides a form to create all node to group relations.
 */
class CreateGroupRelationshipsForm extends FormBase {

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
    $departments = \Drupal::entityTypeManager()->getStorage('group_type')->load('department_site');
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

    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Creating group relationships for @bundle nodes', [
        '@bundle' => $bundle
      ]))
      ->addOperation([EtgrmBatchService::class, 'createNodeData'], [
        ['bundle' => $bundle, 'limit' => 100]
      ])
      ->addOperation([EtgrmBatchService::class, 'createNodeRelationships'], [
        ['bundle' => $bundle, 'limit' => 100]
      ])
      ->setFinishCallback([EtgrmBatchService::class, 'finishProcess']);

    batch_set($batch_builder->toArray());

  }

}
