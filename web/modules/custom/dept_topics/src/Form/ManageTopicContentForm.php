<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Form;

use Drupal\Component\Utility\UrlHelper;
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
 * Provides a form to allow addition, removal and sorting of topic child content.
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

    $form['add_existing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add existing content'),
      '#attributes' => [
        'class' => ['container-inline'],
      ]
    ];

    // Use the Linkit profile to restrict which content can be added.
    $form['add_existing']['add_path'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Content URL'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Start typing a title to search...'),
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
      '#submit' => ['::ajaxSubmit'],
      '#ajax' => [
        'callback' => '::childContentCallback',
        'wrapper' => 'form-wrapper',
      ],
      '#attributes' => [
        'class' => [
          'button--primary',
        ],
      ],
    ];

    $form['topic_nid'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('topic_nid') ?? $nid,
    ];

    // Stores the list of nids that are to be removed when the child content table is recreated.
    $form['removed_children'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('removed_children') ?? '',
    ];

    $form['form_messages'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '',
      '#attributes' => [
        'id' => 'manage-topic-content-form-messages',
      ],
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

    // If this is the first instantiation of the form, load the child contents from the field.
    if (empty($form_state->getValue('child_content'))) {
      $child_contents = $node->get('field_topic_content')->referencedEntities();
    }
    else {
      // Form state only holds the nids, so we load the nodes to access the title.
      $child_contents = $form_state->getValue('child_content');
      if (is_array($child_contents)) {
        $child_contents = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($child_contents));
      }
    }

    foreach ($child_contents as $weight => $child) {

      // Don't add removed child content from the table.
      if (!empty($form['removed_children']['#value'])) {
        if (in_array($child->id(), explode(',', $form['removed_children']['#value']))) {
          continue;
        }
      }

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

      if ($child->bundle() === 'subtopic') {
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
              'manage-topic-content-remove-cell'
            ],
            'title' => $this->t('Edit this subtopic to assign a new parent topic.')
          ],
        ];
      }
      else {
        $form['child_content'][$child_nid]['delete'] = [
          '#type' => 'submit',
          '#title' => t('Remove'),
          '#name' => 'delete_' . $child_nid,
          '#value' => 'Remove',
          '#submit' => ['::ajaxSubmit'],
          '#ajax' => [
            'callback' => '::childContentCallback',
            'wrapper' => 'form-wrapper',
          ],
          '#attributes' => [
            'class' => [
              'button--danger',
              'link',
            ]
          ],
          '#wrapper_attributes' => [
            'class' => [
              'manage-topic-content-remove-cell'
            ],
            'title' => $this->t('Remove this content from the topic.')
          ],
        ];
      }
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
   * Callback to return the child content render array.
   */
  public function childContentCallback(array &$form, FormStateInterface $form_state) {
    // Remove Linkit entry after adding new content.
    $form['add_existing']['add_path']['#value'] = $form_state->getValue('add_path');

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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];

    // Append call.
    if ($parents[0] === 'add') {
      $add_path = $form_state->getValue('add_path');

      if (empty($add_path)) {
        $form_state->setErrorByName('add_path', 'You must provide a URL.');
        return;
      }

      // We only want valid url paths and not the typed text.
      if (!str_starts_with($add_path, '/node/')) {
        $form_state->setErrorByName('add_path', 'Path must be a valid URL');
        return;
      }

      if ($this->subtopicHasParent($this->extractNodeIdFromUrl($add_path))) {
        $form_state->setErrorByName('add_path', 'This subtopic is already assigned to a parent topic and cannot be linked to multiple parent topics.');
        return;
      }

      $child_content = $form_state->getValue('child_content');
      $new_content_nid = $this->extractNodeIdFromUrl($add_path);

      if (is_array($child_content)) {
        // Check if there is already an entry for this node.
        if (array_key_exists($new_content_nid, $child_content)) {
          $form_state->setErrorByName('add_path', 'Child content entry already exists for this URL.');
          return;
        }
      }
    }

    parent::validateForm($form, $form_state);
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

      $child_content = empty($form_state->getValue('child_content')) ? [] : $form_state->getValue('child_content');
      $new_content_nid = $this->extractNodeIdFromUrl($add_path);

      $weight = 0;

      if (is_array($child_content) && !empty($child_content)) {
        $weight = $child_content[array_key_last($child_content)]['weight'];
        $weight++;
      }

      $child_content[$new_content_nid] = [
        'weight' => $weight,
        'delete' => 'Remove',
      ];

      $form_state->setValue('child_content', $child_content);
      // Clear the path value to remove the link in the Linkit field.
      $form_state->setValue('add_path', '');
    }

    // Deleted call.
    if (!empty($parents[2]) && $parents[2] === 'delete') {
      if (!empty($removed_children)) {
        $removed_children = explode(',', $removed_children);
        $removed_children[] = $parents[1];
      }
      else {
        $removed_children = [$parents[1]];
      }

      if (count($removed_children) > 0) {
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

    // TODO: Do a diff on the arrays and only update the field if different.
    $field_topic_content_updated = [];

    // Build our entity reference array and overwrite the existing value.
    if (is_array($child_content)) {
      foreach (array_keys($child_content) as $nid) {
        $field_topic_content_updated[] = ['target_id' => $nid];
      }
    }

    $topic->get('field_topic_content')->setValue($field_topic_content_updated);

    $topic->save();
    $form_state->setRedirect('entity.node.canonical', ['node' => $topic_nid]);
  }

  /**
   * Form cancel handler.
   */
  public function cancel(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.node.canonical', ['node' => $form_state->getValue('topic_nid')]);
  }

  /**
   * Return the node ID for the given path.
   */
  protected function extractNodeIdFromUrl(string $url):int {
    // Strip the host and match the alias to a node id.
    if (UrlHelper::isExternal($url)) {
      $host = $this->getRequest()->getSchemeAndHttpHost();
      $alias = substr($url, strlen($host));
      $path = $this->aliasManager->getPathByAlias($alias);
      $nid = (int) substr($path, 6);
    }
    else {
      // Canonical URL. Trim to extract the node id parameter.
      $nid = (int) substr($url, 6);
    }

    return $nid;
  }

  /**
   * @param int $nid
   *   The node id to check.
   *
   * @return bool
   *   True if the node has parents, otherwise false.
   */
  protected function subtopicHasParent($nid) {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if ($node && $node->bundle() === 'subtopic') {
      return !empty($this->topicManager->getParentNodes($node));
    }
    return FALSE;
  }

}
