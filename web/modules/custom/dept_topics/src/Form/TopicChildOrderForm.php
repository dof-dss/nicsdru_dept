<?php

namespace Drupal\dept_topics\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to order Topic child contents.
 */
final class TopicChildOrderForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dept_topics.topic_child_order';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL): array {

    $form['#title'] = $this->t('Order contents for topic');
    $content = \Drupal::service('entity.form_builder')->getForm($node);

    $children = $node->get('field_topic_content')->getValue();

    $form['topic_content'] = $content['field_topic_content'];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => [$this, 'submitForm'],
        'event' => 'click',
      ],
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand(NULL, 'topicTreeAjaxCallback', [
      $form_state->getValue('field'),
      $form_state->getValue('selected_topics')
    ]));
    $response->addCommand(new CloseDialogCommand());

    return $response;
  }

}
