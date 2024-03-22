<?php

declare(strict_types = 1);

namespace Drupal\dept_topics\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dept_topics\TopicManager;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Departmental sites: topics form.
 */
final class ManageTopicContentForm extends FormBase {

  /**
   * The Topic Manager service.
   *
   * @var \Drupal\dept_topics\TopicManager
   */
  protected $topicManager;

  /**
   * The alias manager that caches alias lookups based on the request.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ModerationStateChangeSubscriber object.
   *
   * @param \Drupal\dept_topics\TopicManager $topic_manager
   *   The Topic Manager service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(TopicManager $topic_manager, AliasManagerInterface $alias_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->topicManager = $topic_manager;
    $this->aliasManager = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('topic.manager'),
      $container->get('path_alias.manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dept_topics_add_existing_content';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $nid = $this->getRequest()->query->get('nid');
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    // TODO: Rename this library and update css
    $form['#attached']['library'][] = 'dept_topics/manage_topic_content';

    $form['add_existing'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ]
    ];

    $form['add_existing']['add_path'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Add existing content'),
      '#description_display' => 'after',
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'topic_child_content',
      ],
    ];

    $form['add_existing']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#name' => 'add',
      '#value' => 'Add',
      '#submit' => ['::ajaxSubmit'],
      '#ajax' => [
        'callback' => '::addMoreSet',
        'wrapper' => 'child-content-wrapper',
      ]
    ];


    $form['topic_nid'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('topic_nid') ?? $nid,
    ];

    $form['removed_children'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('removed_children') ?? '',
    ];

    $form['child_content'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#prefix' => '<div id="child-content-wrapper">',
      '#suffix' => '</div>',
      '#empty' => $this->t('This topic has no child content'),
      '#tabledrag' => [[
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
      ]]
    ];

    // If this is the first instantiation of the form, load the child contents from the field.
    if (empty($form_state->getValue('child_content'))) {
      $child_contents = $node->get('field_topic_content')->referencedEntities();
    } else {
      // Form state only holds the nids, load the nodes so we can access the title.
      $child_contents = $form_state->getValue('child_content');
      $child_contents = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($child_contents));
    }

    foreach ($child_contents as $weight => $child) {

      if (!empty($form['removed_children']['#value'])) {
        if (in_array($child->id(), explode(',', $form['removed_children']['#value']))) {
          continue;
        }
      }

      $cnid = $child->id();
      $form['child_content'][$cnid]['#attributes']['class'][] = 'draggable';
      $form['child_content'][$cnid]['#weight'] = $weight;
      $form['child_content'][$cnid]['title'] = [
        '#markup' => $child->label(),
      ];
      $form['child_content'][$cnid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $child->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ];
      $form['child_content'][$cnid]['delete'] = [
        '#type' => 'submit',
        '#title' => t('Remove'),
        '#name' => 'delete_' . $cnid,
        '#value' => 'Remove',
        '#submit' => ['::ajaxSubmit'],
        '#ajax' => [
          'callback' => '::addMoreSet',
          'wrapper' => 'child-content-wrapper',
        ]
      ];
    }

    $form['actions'] = ['#type' => 'actions',];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancel'],
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => [
          'manage-topic-content-cancel',
        ]
      ]
    ];

    return $form;
  }

  public function addMoreSet(array &$form, FormStateInterface $form_state) {
    return $form['child_content'];
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    $removed_children = $form_state->getValue('removed_children');

    // Append call.
    if ($parents[0] === 'add') {
      $add_path = $form_state->getValue('add_path');

      $host = $this->getRequest()->getSchemeAndHttpHost();
      $alias = substr($add_path, strlen($host));
      $path = $this->aliasManager->getPathByAlias($alias);

      $child_content = $form_state->getValue('child_content');
      $nid = substr($path, 6);

      $weight = $child_content[array_key_last($child_content)]['weight'];
      $weight++;

      $child_content[$nid] = [
        'weight' => $weight,
        'delete' => 'Remove',
      ];

      $form_state->setValue('child_content', $child_content);
    }

    // Deleted call.
    if (!empty($parents[2]) && $parents[2] === 'delete') {
      if (!empty($removed_children)) {
        $removed_children = explode(',', $removed_children);
        $removed_children[] = $parents[1];
      } else {
        $removed_children = [$parents[1]];
      }

      if (!empty($removed_children)) {
        $form_state->setValue('removed_children', implode(',', $removed_children));
      }
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $child_content = $form_state->getValue('child_content');
    $topic_nid = $form_state->getValue('topic_nid');

    $topic = $this->entityTypeManager->getStorage('node')->load($topic_nid);

    $vals = $topic->get('field_topic_content')->getValue();
    // TODO: Do a diff on the arrays and only update the field if different.

    $new_vals = [];

    foreach (array_keys($child_content) as $nid) {
      $new_vals[] = ['target_id' => $nid];
    }

    $topic->get('field_topic_content')->setValue($new_vals);
    $topic->save();

    $form_state->setRedirect('entity.node.canonical', ['node' => $topic_nid]);

  }

}
