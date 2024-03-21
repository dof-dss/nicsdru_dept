<?php

declare(strict_types = 1);

namespace Drupal\dept_topics\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dept_topics\TopicManager;
use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a Departmental sites: topics form.
 */
final class AddExistingContentForm extends FormBase {

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

    $form['#attached']['library'][] = 'dept_topics/child_order';

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

    $child_contents = $node->get('field_topic_content')->referencedEntities();

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
        '#name' => 'delete_' . $weight,
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

    if (!empty($removed_children)) {
      $removed_children = explode(',', $removed_children);
      $removed_children[] = $parents[1];
    } else {
      $removed_children = [$parents[1]];
    }

    if (!empty($removed_children)) {
      $form_state->setValue('removed_children', implode(',', $removed_children));
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
    $iterator = $topic->get('field_topic_content')->getIterator();

    $new_vals = [];

    foreach (array_keys($child_content) as $nid) {
      $new_vals[] = ['target_id' => $nid];
    }

    $topic->get('field_topic_content')->setValue($new_vals);
    $topic->save();

    $form_state->setRedirect('entity.node.canonical', ['node' => $topic_nid]);

  }

}
