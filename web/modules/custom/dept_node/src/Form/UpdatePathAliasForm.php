<?php

namespace Drupal\dept_node\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for altering a node path alias.
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
   * @param string $nid
   *   The node id of the path alias.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = '') {
    $source = '/node/' . $nid;
    $results = \Drupal::database()->query("SELECT pa.path, pa.alias FROM path_alias pa WHERE pa.path = :source", [':source' => $source])->fetchCol(1);

    $form['#prefix'] = '<div id="update-path-alias-form">';
    $form['#suffix'] = '</div>';

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

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('cancel'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'closeModalAjax'],
        'event' => 'click',
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * Ajax callback for form submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#update-path-alias-form', $form));
    }
    else {
      $node_path = '/node/' . $form_state->getValue('nid');

      \Drupal::database()->update('path_alias')
        ->fields(['alias' => $form_state->getValue('new_alias')])
        ->condition('path', $node_path, '=')
        ->execute();

      $response->addCommand(new RedirectCommand(Url::fromRoute('entity.node.canonical', ['node' => $form_state->getValue('nid')])->toString()));
    }

    return $response;
  }

  /**
   * Ajax callback to close the modal.
   */
  public function closeModalAjax() {
    $command = new CloseModalDialogCommand();
    $response = new AjaxResponse();
    $response->addCommand($command);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $results = \Drupal::database()->query("SELECT pa.path, pa.alias FROM path_alias pa WHERE pa.alias = :alias AND pa.path <> :path ", [
      ':alias' => $values['new_alias'],
      ':path' => '/node/' . $values['nid'],
    ])->fetchCol(1);

    if ($results) {
      $form_state->setErrorByName('new_alias', 'Alias already in use, please try another');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
