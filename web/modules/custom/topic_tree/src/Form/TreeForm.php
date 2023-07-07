<?php declare(strict_types = 1);

namespace Drupal\topic_tree\Form;

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


    // Search filter box.
    $form['tree_search'] = [
      '#type' => 'textfield',
      '#title' => $this
        ->t('Search'),
      '#size' => 60,
      '#attributes' => [
        'id' => [
          'entity-reference-tree-search',
        ],
      ],
    ];

    // JsTree container.
    $form['tree_container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['jstree'],
        'id' => ['topic-tree-wrapper'],
      ],
    ];
    // Submit button.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitForm'],
        'event' => 'click',
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
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if (mb_strlen($form_state->getValue('message')) < 10) {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('Message should be at least 10 characters.'),
    //     );
    //   }
    // @endcode
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('The message has been sent.'));
    $form_state->setRedirect('<front>');
  }

}
