<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dept_topics\TopicManager;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to allow re-ordering of topic child content.
 */
final class ManageTopicContentForm extends FormBase {

  /**
   * The Topic manager service.
   *
   * @var \Drupal\dept_topics\TopicManager
   */
  protected $topicManager;

  /**
   * The Alias manager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\dept_topics\TopicManager $topic_manager
   *   The Topic manager service.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The Alias manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type manager.
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
    return 'dept_topics_manage_topic_content';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $nid = $this->getRequest()->query->get('nid');
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    $form['#attached']['library'][] = 'dept_topics/manage_topic_content';

    $form['#prefix'] = '<div id="form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['topic_nid'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('topic_nid') ?? $nid,
    ];

    $form['child_content'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#empty' => $this->t('This topic has no child content.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ]
      ]
    ];

    $child_contents = $node->get('field_topic_content')->referencedEntities();

    foreach ($child_contents as $weight => $child) {
      $child_nid = $child->id();
      $form['child_content'][$child_nid]['#attributes']['class'][] = 'draggable';
      $form['child_content'][$child_nid]['#weight'] = $weight;

      $form['child_content'][$child_nid]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $child->label(),
      ];

      if (!$child->isPublished()) {
        $state = $child->get('moderation_state')->getString();
        $form['child_content'][$child_nid]['title']['#suffix'] = ' <span title="Moderation status" class="moderation-state--' . str_replace('_', '-', $state) . '">' . ucfirst(str_replace('_', ' ', $state)) . '</span>';
      }

      $form['child_content'][$child_nid]['weight'] = [
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

      $form['child_content'][$child_nid]['edit'] = [
        '#type' => 'link',
        '#title' => t('Edit'),
        '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $child->id()]),
        '#attributes' => [
          'class' => ['button--danger', 'link', 'button'],
          'title' => t('Edit this subtopic to assign a new parent topic'),
          'target' => '_blank',
        ],
        '#wrapper_attributes' => [
          'class' => [
            'manage-topic-content-remove-cell',
          ],
          'title' => $this->t('Edit this subtopic to assign a new parent topic.'),
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => [
        'class' => [
          'button--primary',
        ],
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancel'],
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => [
          'manage-topic-content-cancel',
          'button--danger',
          'use-ajax'
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'closeModalAjax'],
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * Ajax callback to close the modal.
   */
  public function closeModalAjax() {
    $command = new CloseModalDialogCommand();
    $response = new AjaxResponse();
    $response->addCommand($command);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $child_content = $form_state->getValue('child_content');
    $topic_nid = $form_state->getValue('topic_nid');

    $topic = $this->entityTypeManager->getStorage('node')->load($topic_nid);

    // TODO: Do a diff on the arrays and only update the field if different.
    $field_topic_content_updated = [];

    // Build our entity reference array and overwrite the existing value.
    if (is_array($child_content)) {
      foreach (array_keys($child_content) as $nid) {
        $field_topic_content_updated[] = ['target_id' => $nid];
      }
    }

    $topic->get('field_topic_content')->setValue($field_topic_content_updated);
    $topic->setRevisionTranslationAffected(TRUE);
    $topic->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $topic->setRevisionUserId(\Drupal::currentUser()->id());
    $topic->save();
    $form_state->setRedirect('entity.node.canonical', ['node' => $topic_nid]);
  }

  /**
   * Form cancel handler.
   */
  public function cancel(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.node.canonical', ['node' => $form_state->getValue('topic_nid')]);
  }

}
