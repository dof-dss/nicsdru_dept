<?php

/**
 * @file
 * HOOK_ALTER() hooks for departmental topics.
 */

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_moderation_sidebar_alter().
 */
function dept_topics_moderation_sidebar_alter(&$build, &$context) {
  if ($context instanceof NodeInterface &&
    in_array($context->bundle(), ['topic', 'subtopic'])) {

    // See also: dept_postprocess_moderation_sidebar_alter()
    if (\Drupal::currentUser()->hasPermission('manage order of topic content')) {

      $build['actions']['secondary']['info_topic_content'] = [
        '#theme' => 'moderation_sidebar_info_section',
        '#text' => 'Manage topic content',
        '#tag' => 'h2'
      ];

      // Link to pop modal to manage the topic contents.
      $build['actions']['secondary']['manage_existing'] = [
        '#title' => t('Manage existing content'),
        '#type' => 'link',
        '#url' => Url::fromRoute('dept_topics.manage_topic_content.form', ['nid' => $context->id()]),
        '#attributes' => [
          'class' => [
            'moderation-sidebar-link',
            'button',
            'button--tertiary',
            'use-ajax'
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'title' => t('Manage topic content'),
            'width' => '1000',
            'minHeight' => 500,
            'position' => ['my' => 'center top', 'at' => 'center top'],
            'draggable' => TRUE,
            'autoResize' => TRUE,
          ]),
        ],
      ];

      // Links to create new content for topics, generated from allowed target
      // bundles configuration for the field_topic_content field.
      $bundle_fields = \Drupal::getContainer()
        ->get('entity_field.manager')
        ->getFieldDefinitions('node', 'topic');
      $field_definition = $bundle_fields['field_topic_content'];
      $target_bundles = $field_definition->getSetting('handler_settings')['target_bundles'];

      $build['actions']['secondary']['info_add_new'] = [
        '#theme' => 'moderation_sidebar_info_section',
        '#text' => 'Quickly add new...',
        '#tag' => 'p'
      ];

      $current_dept = \Drupal::service('department.manager')
        ->getCurrentDepartment();

      foreach ($target_bundles as $bundle => $label) {
        // Replace hard-coded rule with permissions?
        if ($current_dept->id() != 'daera' && $bundle === 'protected_area') {
          continue;
        }

        $type = NodeType::load($bundle);

        $build['actions']['secondary']['add_' . $bundle] = [
          '#title' => ucfirst($type->label()),
          '#type' => 'link',
          '#url' => Url::fromRoute('node.add', ['node_type' => $bundle], ['query' => ['topic' => $context->id()]]),
          '#attributes' => [
            'class' => [
              'moderation-sidebar-link',
              'button',
              'button--secondary'
            ],
          ],
        ];
      }

      $build['#attached']['library'][] = 'dept_topics/moderation_sidebar';
    }
  }
}

/**
 * Implements hook_metatags_attachments_alter().
 */
function dept_topics_metatags_attachments_alter(array &$metatag_attachments) {
  $topicManager = \Drupal::service('topic.manager');
  $node = \Drupal::routeMatch()->getParameter('node');

  if ($node instanceof NodeInterface && in_array($node->bundle(), $topicManager->getTopicChildNodeTypes())) {
    $topicManager = \Drupal::service('topic.manager');
    $parents = $topicManager->getParentNodes($node->id());
    $tag_index = -1;

    if ($parents) {
      foreach ($metatag_attachments['#attached']['html_head'] as $index => $tag) {
        preg_match_all('/article_tag_(\d)/m', $tag[1], $matches, PREG_SET_ORDER, 0);

        if ($matches) {
          $tag_index = $matches[0][1];
        }
      }

      // Create a new article metatag for each parent node.
      $tag_index++;
      foreach ($parents as $parent) {
        $metatag_attachments['#attached']['html_head'][] =
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => "article:tag",
                'content' => $parent->title
              ]
            ],
            'article_tag_' . $tag_index,
          ];
        $tag_index++;
      }
    }
  }
}

/**
 * Implements hook_form_ENTITY_form_alter().
 */
function dept_topics_form_node_form_alter(&$form, $form_state) {
  $form_object = $form_state->getFormObject();
  $bundle = $form_object->getEntity()->bundle();
  $topic_id = \Drupal::request()->query->get('topic');

  // Set the Site Topic to 'Historic environment' for Heritage sites.
  if ($bundle === 'heritage_site') {
    // Select the site topic passed by the querystring parameter (coming from the moderation sidebar).
    if (array_key_exists('field_site_topics', $form)) {
      $form['field_site_topics']['widget']['#default_value'] = [HISTORIC_ENVIRONMENT_NID];
    }
  }

  // Populate the field_site_topics form element if the topic_id was present in
  // the querystring (Used by the 'quick add' feature in the moderation sidebar)
  if (!empty($topic_id)) {
    if (array_key_exists('field_site_topics', $form)) {
      $form['field_site_topics']['widget']['#default_value'] = [$topic_id];
    }
  }

  if ($bundle === 'topic' || $bundle === 'subtopic') {
    $form['actions']['submit']['#submit'][] = 'dept_topics_enable_domain_path';
  }
}

/**
 * Implements hook_form_alter().
 */
function dept_topics_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  // Update Linkit profile target bundles when the subtopic field_child_content
  // configuration is updated.
  if ($form_id === 'field_config_edit_form' && !empty($form['#entity'])) {
    if ($form['#entity']->bundle() === 'subtopic') {
      $form['actions']['submit']['#submit'][] = 'dept_topics_update_linkit_targets';
    }
  }

  if (in_array($form_id, ['node_topic_form', 'node_topic_edit_form', 'node_subtopic_form', 'node_subtopic_edit_form'])) {
    $form['#validate'][] = 'dept_topics_validate_topics';
  }
}

/**
 * Implements hook_views_query_alter().
 */
function dept_topics_views_query_alter(ViewExecutable $view, QueryPluginBase $query) {
  if ($view->id() === 'content_by_site_subtopic') {
    // @phpstan-ignore-next-line
    foreach ($query->where[0]['conditions'] as $index => $condition) {
      if (str_starts_with($condition['field'], 'node__field_site_topics')) {
        // Fetch current topic node id.
        $topic = \Drupal::routeMatch()->getParameter('node');
        if ($topic instanceof NodeInterface === FALSE) {
          return;
        }

        $args[] = $topic->id();

        // Subtopics to include.
        /** @var \Drupal\dept_topics\TopicManager $topic_manager */
        $topic_manager = \Drupal::service('topic.manager');
        $subtopics = $topic_manager->getTopicChildren($topic);

        if (!empty($subtopics)) {
          $subtopics = array_keys($subtopics);
        }

        $args = array_merge($args, $subtopics);

        // @phpstan-ignore-next-line
        $query->where[0]['conditions'][$index]['field'] = 'node__field_site_topics.field_site_topics_target_id';
        $query->where[0]['conditions'][$index]['value'] = $args;
        $query->where[0]['conditions'][$index]['operator'] = 'in';
      }
    }
  }
}

/**
 * Implements hook_entity_bundle_field_info_alter().
 */
function dept_topics_entity_bundle_field_info_alter(&$fields, EntityTypeInterface $entity_type, $bundle) {
  if ($bundle === 'topic' || $bundle === 'subtopic') {
    if (isset($fields['field_topic_content'])) {
      $fields['field_topic_content']->addConstraint('unique_list_items');
    }
  }
}
