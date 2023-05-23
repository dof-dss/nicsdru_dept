<?php

namespace Drupal\dept_node\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeForm;

/**
 * Form handler for the node add/edit forms.
 *
 * Provides additional functionality to process child nodes to Topica/Subtopics.
 *
 * #internal Drupal\node\NodeForm.
 *
 */
class DeptNodeForm extends NodeForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $node = $this->entity;
    $insert = $node->isNew();
    $result = $node->save();
    $node_link = $node->toLink($this->t('View'))->toString();
    $context = [
      // @phpstan-ignore-next-line
      '@type' => $node->getType(),
      '%title' => $node->label(),
      'link' => $node_link
    ];
    $t_args = [
      '@type' => node_get_type_label($node),
      '%title' => $node->toLink()->toString()
    ];

    if ($insert) {
      $this->logger('content')->notice('@type: added %title.', $context);
      $this->messenger()->addStatus($this->t('@type %title has been created.', $t_args));
    }
    else {
      $this->logger('content')->notice('@type: updated %title.', $context);
      $this->messenger()->addStatus($this->t('@type %title has been updated.', $t_args));
    }

    if ($node->id()) {
      $form_state->setValue('nid', $node->id());
      $form_state->set('nid', $node->id());
      if ($node->access('view')) {
        $form_state->setRedirect(
          'entity.node.canonical',
          ['node' => $node->id()]
        );
      }
      else {
        $form_state->setRedirect('<front>');
      }

      // Remove the preview entry from the temp store, if any.
      $store = $this->tempStoreFactory->get('node_preview');
      $store->delete($node->uuid());
    }
    else {
      // In the unlikely case something went wrong on save, the node will be
      // rebuilt and node form redisplayed the same way as in preview.
      $this->messenger()->addError($this->t('The post could not be saved.'));
      $form_state->setRebuild();
    }

    // DEPARTMENTAL ALTERATIONS (everything above is Drupal Core)
    $topic_id = \Drupal::request()->get('topic');

    if (!empty($topic_id)) {
      $topic = $this->entityTypeManager->getStorage('node')->load($topic_id);

      if (!empty($topic)) {
        $topic->get('field_topic_content')->appendItem([
          'target_id' => $node->id()
        ]);
        $topic->save();
      }
    }
    return $result;
  }

}
