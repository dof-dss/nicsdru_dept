<?php

namespace Drupal\dept_node\Form;

use Drupal\Core\Entity\ContentEntityForm;
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
    ksm($form, $form_state);
    // DRUPAL CORE CODE - DO NOT EDIT (unless there are core changes)
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

    if ($form_state->hasValue('field_site_topics') && $node->moderation_state->value === 'published') {
      $form_object = $form_state->getFormObject();

      $site_topics_original = $form['field_site_topics']['widget']['#default_value'];
      $site_topics_new = $form_state->getValue('field_site_topics');
      $site_topics_removed = array_diff($site_topics_original, $site_topics_new);

      if ($form_object instanceof ContentEntityForm) {
        $form_display = $this->entityTypeManager->getStorage('entity_form_display')->load('node' . '.' . $node->bundle() . '.' . 'default');
        $specific_widget_type = $form_display->getComponent('field_site_topics');

        $child_bundles = array_keys($specific_widget_type['settings'],'1');

        if (in_array($node->bundle(), $child_bundles)) {
          $node_storage = $this->entityTypeManager->getStorage('node');

          foreach ($site_topics_new as $new) {
            $topic_node = $node_storage->load($new);
            $topic_node->get('field_topic_content')->appendItem([
              'target_id' => $node->id()
            ]);
            $topic_node->save();

          }
        }
      }
    }

    return $result;
  }

}
