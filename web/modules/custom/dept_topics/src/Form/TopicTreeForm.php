<?php

namespace Drupal\dept_topics\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

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
  public function buildForm(array $form, FormStateInterface $form_state, $department = NULL, $field = NULL, $limit = NULL, $selected = NULL, $nid = NULL): array {

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
        'placeholder' => $this->t('Search topics...'),
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

    // ID of the field the tree is linked to.
    $form['field'] = [
      '#type' => 'hidden',
      '#default_value' => $field,
    ];

    // String of selected topics ID's from the tree.
    $form['selected_topics'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => ['field-site-topics'],
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
    ];

    $form['#attached']['library'][] = 'dept_topics/jstree';
    $form['#attached']['library'][] = 'dept_topics/topic_tree';
    $form['#attached']['library'][] = 'dept_topics/jstree_theme';

    // Data to pass to the JsTree instance.
    $form['#attached']['drupalSettings'] = [
      'topic_tree.department' => $department,
      'topic_tree.field' => $field,
      'topic_tree.selected' => $selected,
      'topic_tree.limit' => $limit,
      'topic_tree.current_nid' => $nid,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $topic_values = [];

    // Normalise the composite ids used to make JSTree items unique.
    $selected_topic_values = $form_state->getValue('selected_topics');
    if (!empty($selected_topic_values)) {
      $selected_topic_values = explode(',', $selected_topic_values);

      foreach ($selected_topic_values as $selection) {
        if (str_contains($selection, '--')) {
          $topic_values[] = explode('--', $selection)[0];
        }
      }
    }

    if (empty($topic_values)) {
      $topic_values = $selected_topic_values;
    }

    // Convert back to a string for use by the JS function callback.
    $topic_values = is_array($topic_values) ? implode(',', $topic_values) : '';

    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand(NULL, 'topicTreeAjaxCallback', [
      $form_state->getValue('field'),
      $topic_values
    ]));
    $response->addCommand(new CloseDialogCommand());

    return $response;
  }

}
