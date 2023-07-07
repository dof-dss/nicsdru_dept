<?php

namespace Drupal\topic_tree\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Topic tree form.
 */
final class TreeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'topic_tree_tree';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['tree_container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['jstree'],
        'id' => ['topic-tree-wrapper'],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => [$this, 'submitForm'],
        'event' => 'click',
      ],
      '#attributes' => [
        'class' => ['use-ajax'],
      ],
    ];

    $form['#attached']['library'][] = 'topic_tree/jstree';
    $form['#attached']['library'][] = 'topic_tree/topic_tree';
    $form['#attached']['library'][] = 'topic_tree/jstree_theme';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand());

    return $response;
  }

}
