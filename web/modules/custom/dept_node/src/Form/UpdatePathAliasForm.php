<?php

namespace Drupal\dept_node\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Departmental sites: node form.
 */
class UpdatePathAliasForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dept_node_update_path_alias';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $source
   *   The source system path.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = '') {

    $dbConn = \Drupal::database();
    $source = '/node/' . $nid;

    $results = $dbConn->query("SELECT pa.path, pa.alias FROM path_alias pa WHERE pa.path = :source", [':source' => $source])->fetchCol(1);

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['new_alias'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path alias'),
      '#default_value' => $results[0],
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $dbConn = \Drupal::database();
    $node_path = '/node/' . $form_state->getValue('nid');

    $results = $dbConn->query("SELECT pa.path, pa.alias FROM path_alias pa WHERE pa.path = :source", [':source' => $source])->fetchCol(1);

    $result = $dbConn->update('path_alias')
           ->fields(['alias' => $form_state->getValue('new_alias')])
           ->condition('path', $node_path , '=')
           ->execute();

    $form_state->setRedirect('entity.node.canonical', ['node' => $form_state->getValue('nid')]);
  }

}
