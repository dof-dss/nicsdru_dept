<?php

namespace Drupal\dept_topics\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

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

    $form['#title'] = $this->t('Order contents for %title', ['%title' => $node->label()]);


    $node_form = \Drupal::service('entity.form_builder')->getForm($node);
    $form['field_topic_content'] = $node_form['field_topic_content'];

    ksm($node_form);

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];


    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
//      '#ajax' => [
//        'callback' => [$this, 'submitForm'],
//        'event' => 'click',
//      ],
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    ksm($form);
    $nid = $form_state->getValue('nid');
//    $children = array_column($form_state->getValue('field_topic_content'), 'target_id');
    $children = $form_state->getValue('field_topic_content');
    $updated = [];

    ksm($children);

//    unset($children['add_more']);

//    foreach ($children as $child) {
//      $updated[] = [
//        'target_id' => $child['target_id']
//      ];
//    }

//w7}aSfNt
//
//    $node = Node::load($nid);
//
//    $node->field_topic_content = $children;
//    $node->save();

//    $response = new AjaxResponse();
//    ksm($nid, $children);
//    $response->addCommand(new CloseDialogCommand());

    return $response;
  }

}
