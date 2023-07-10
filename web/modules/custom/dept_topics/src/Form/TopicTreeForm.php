<?php

namespace Drupal\dept_topics\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Topic tree form.
 */
final class TopicTreeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'topic_tree_tree';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $department = NULL, $field = NULL): array {

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

    $form['#attached']['library'][] = 'dept_topics/jstree';
    $form['#attached']['library'][] = 'dept_topics/topic_tree';
    $form['#attached']['library'][] = 'dept_topics/jstree_theme';

    $form['#attached']['drupalSettings'] = [
      'topic_tree.department' => $department,
      'topic_tree.field' => $field,
    ];

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
