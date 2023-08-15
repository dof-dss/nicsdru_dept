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
    return 'dept_topics.topic_tree';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $department = NULL, $field = NULL, $limit = NULL, $selected = NULL): array {

    $form['#title'] = $this->t('Select topic');

    $form['selection_count'] = [
      '#type' => 'html_tag',
      '#tag' => 'strong',
      '#value' => 'Selected: <span>0</span> of ' . $limit . ' topics',
      '#attributes' => [
        'id' => 'topic-tree-count',
        'style' => ['float: right'],
      ]
    ];

    $form['tree_search'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'id' => 'topic-tree-search',
        'placeholder' => 'Search topics...'
      ],
    ];

    // Container to load JsTree into.
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

    // Data to pass to the JsTree instance.
    $form['#attached']['drupalSettings'] = [
      'topic_tree.department' => $department,
      'topic_tree.field' => $field,
      'topic_tree.selected' => $selected,
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
