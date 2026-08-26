<?php

/**
 * @file
 * Contains hook_alter() functions for Topics.
 */

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_moderation_sidebar_alter().
 */
function dept_topics_moderation_sidebar_alter(&$build, &$context) {
  // NOTE: See also dept_postprocess for additional moderation sidebar changes.
  if ($context instanceof NodeInterface &&
    in_array($context->bundle(), ['topic', 'subtopic'])) {

    $has_active_children = \Drupal::service('topic.manager')->topicHasActiveChildren($context);

    // If the topic has active child content, disable the archive button.
    if (isset($build["actions"]["primary"]["quick_draft_form"]) &&
      array_key_exists('archive', $build["actions"]["primary"]["quick_draft_form"]) &&
      $has_active_children) {

      $build["actions"]["primary"]["quick_draft_form"]['archive']['#attributes']['disabled'] = 'disabled';
      $build["actions"]["primary"]["quick_draft_form"]['archive']['#attributes']['style'] = "cursor: not-allowed";
      $build["actions"]["primary"]["quick_draft_form"]['archive']['#attributes']['title'] = "This content has active child pages. It cannot be archived until child pages have been reallocated to a different topic, archived or deleted.";
    }

    // If the topic has active child content, disable the delete button.
    if (isset($build["actions"]["primary"]["delete"]) && $has_active_children) {
      $build["actions"]["primary"]["delete"] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => t('Delete'),
        '#weight' => 1,
        '#attributes' => [
          'class' => ['moderation-sidebar-link', 'button', 'button--danger'],
          'disabled' => 'disabled',
          'style' => "cursor: not-allowed",
          'title' => t('This content has active child pages. It cannot be deleted until child pages have been reallocated to a different topic, archived or deleted.'),
        ],
      ];
    }

    $user = Drupal::currentUser();

    // See also: dept_postprocess_moderation_sidebar_alter()
    if ($user->hasPermission('manage order of topic content')) {

      $build['actions']['secondary']['info_topic_content'] = [
        '#theme' => 'moderation_sidebar_info_section',
        '#text' => 'Manage topic content',
        '#tag' => 'h2'
      ];

      // Add a link to display a modal allowing reordering of child content.
      $build['actions']['secondary']['manage_existing'] = [
        '#title' => t('Arrange Content'),
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
            'title' => t('Arrange topic content'),
            'width' => '1000',
            'minHeight' => 500,
            'position' => ['my' => 'center top', 'at' => 'center top'],
            'draggable' => TRUE,
            'autoResize' => TRUE,
          ]),
        ],
      ];

      // Links to create new content for topics, generated from target bundles of the field .
      $bundle_fields = \Drupal::getContainer()
        ->get('entity_field.manager')
        ->getFieldDefinitions('node', 'topic');
      $field_definition = $bundle_fields['field_topic_content'];
      $target_bundles = $field_definition->getSetting('handler_settings')['target_bundles'];

      $build['actions']['secondary']['info_add_new'] = [
        '#theme' => 'moderation_sidebar_info_section',
        '#text' => 'Add new content',
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

    // Link to display a tree view of topics and child content.
    if ($user->hasPermission('view topics structure report')) {
      $build['actions']['primary']['topic_structure_report'] = [
        '#title' => t('Topic structure report'),
        '#type' => 'link',
        '#url' => Url::fromRoute('dept_topics.topic_structure_report', ['node' => $context->id()]),
        '#attributes' => [
          'class' => [
            'moderation-sidebar-link',
            'button',
          ],
        ],
      ];
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
        preg_match_all('/article_tag_(\d+)/m', $tag[1], $matches, PREG_SET_ORDER, 0);

        if ($matches) {
          $tag_index = $matches[0][1];
        }
      }

      // Create a new article tag for each parent node.
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
 * Implements hook_form_ENTITY_form_alter().
 */
function dept_topics_form_node_form_alter(&$form, $form_state, $form_id) {
  $form_object = $form_state->getFormObject();
  $entity = $form_object->getEntity();
  $topic_id = \Drupal::request()->query->get('topic');

  // Set the Site Topic to 'Historic environment' for Heritage sites.
  if ($entity->bundle() === 'heritage_site') {
    // Select the site topic passed by the querystring parameter (coming from the moderation sidebar).
    if (array_key_exists('field_site_topics', $form)) {
      $form['field_site_topics']['widget']['#default_value'] = [HISTORIC_ENVIRONMENT_NID];
      $form['field_site_topics']['#disabled'] = TRUE;
    }
  }

  if (!empty($topic_id)) {
    // Select the site topic passed by the querystring parameter (coming from the moderation sidebar).
    if (array_key_exists('field_site_topics', $form)) {
      $form['field_site_topics']['widget']['#default_value'] = [$topic_id];
    }
  }

  if ($entity->bundle() === 'topic' || $entity->bundle() === 'subtopic') {
    $form['actions']['submit']['#submit'][] = 'dept_topics_enable_domain_path';

    if ($entity->isNew() === FALSE && \Drupal::service('topic.manager')->topicHasActiveChildren($entity)) {

      // Prevent the selection of 'Archived' if the topic has active child content.
      if (array_key_exists('archived', $form["moderation_state"]["widget"][0]["state"]["#options"])) {
        unset($form["moderation_state"]["widget"][0]["state"]["#options"]['archived']);
      }

      // Replace the delete button with a disabled version when active child content exists.
      $form["actions"]["delete"] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => t('Delete'),
        '#weight' => 100,
        '#attributes' => [
          'class' => ['action-link', 'action-link--danger', 'action-link--icon-trash'],
          'disabled' => 'disabled',
          'style' => "cursor: not-allowed",
          'title' => t('This content has active child pages. It cannot be deleted until child pages have been reallocated to a different topic, archived or deleted.'),
        ],
      ];
    }
  }
}

/**
 * Implements hook_form_alter().
 */
function dept_topics_form_alter(&$form, FormStateInterface $form_state, $form_id) {

  if ($form_id === 'field_config_edit_form' && !empty($form['#entity'])) {
    if ($form['#entity']->bundle() === 'subtopic') {
      $form['actions']['submit']['#submit'][] = 'dept_topics_update_linkit_targets';
    }
  }

  if (in_array($form_id, ['node_topic_form', 'node_topic_edit_form', 'node_subtopic_form', 'node_subtopic_edit_form'])) {
    $form['#validate'][] = 'dept_topics_validate_topics';
    $form['#attached']['library'][] = 'dept_topics/topic_admin';
  }

  // Prevent users from reverting a topic to an archived revision if it has active
  // child content.
  if ($form_id === 'node_revision_revert_confirm') {
    $node = \Drupal::routeMatch()->getParameter('node_revision');

    if ($node instanceof NodeInterface && in_array($node->bundle(), ['topic', 'subtopic'])) {
      $has_active_children = \Drupal::service('topic.manager')->topicHasActiveChildren($node);

      if ($has_active_children && $node->get('moderation_state')->value === 'archived') {
        $form['notice'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => t('This @bundle has active child pages. It cannot be reverted to an archived state until child pages have been reallocated to a different topic, archived or deleted. ', [
            '@bundle' => $node->bundle()
          ]),
        ];
        $form['actions']['submit']['#disabled'] = TRUE;
      }

    }
  }

  // Remove the 'archive' scheduled transition option for topics/subtopics with
  // non-archived children. The form ID includes the bundle, e.g.
  // node_topic_scheduled_transitions_add_form_form.
  if (str_contains($form_id, 'scheduled_transitions_add_form')) {
    $node = \Drupal::routeMatch()->getParameter('node');

    if ($node instanceof NodeInterface && in_array($node->bundle(), ['topic', 'subtopic']) &&
      isset($form['scheduled_transitions']['new_meta']['transition']['#options']['archive'])) {

      if (\Drupal::service('topic.manager')->topicHasActiveChildren($node)) {
        unset($form['scheduled_transitions']['new_meta']['transition']['#options']['archive']);
      }
    }
  }

  // Prevent deletion of Topics or Subtopics if they have active child content.
  if (in_array($form_id, ['node_topic_delete_form', 'node_subtopic_delete_form'])) {
    // @phpstan-ignore-next-line.
    $node = $form_state->getFormObject()->getEntity();

    if (\Drupal::service('topic.manager')->topicHasActiveChildren($node)) {
      $form['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => t("This @bundle '%title' cannot be deleted until all child pages have been reallocated to a different topic, archived or deleted.", [
          '@bundle' => $node->bundle(),
          '%title' => $node->label(),
        ]),
      ];

      $form['actions']['submit']['#disabled'] = TRUE;
    }
  }
}
