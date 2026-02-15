<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityStorageInterface;
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
   * Node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $nodeStorage;

  /**
   * Constructor.
   */
  public function __construct(
    private readonly TopicManager $topicManager,
    private readonly AliasManagerInterface $aliasManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $nid = $this->getRequest()->query->get('nid');
    $node = $nid ? $this->nodeStorage->load($nid) : NULL;

    $form['#attached']['library'][] = 'dept_topics/manage_topic_content';

    $form['#prefix'] = '<div id="form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['add_existing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add existing content'),
      '#attributes' => [
        'class' => ['container-inline'],
      ],
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
        'class' => ['button--primary'],
      ],
    ];

    $form['topic_nid'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('topic_nid') ?? $nid,
    ];

    // Stores the list of nids that are to be removed when the table is recreated.
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

    $form['subtopics_info'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Subtopics can only be removed if they are assigned a new site topic. This can be achieved using the edit link which opens in a new window.'),
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
        ],
      ],
    ];

    // If first instantiation, load child contents from the field.
    if (empty($form_state->getValue('child_content'))) {
      $child_contents = ($node && $node->hasField('field_topic_content'))
        ? $node->get('field_topic_content')->referencedEntities()
        : [];
    }
    else {
      // Form state holds nids, so load nodes to access labels.
      $child_contents = $form_state->getValue('child_content');
      if (is_array($child_contents)) {
        $child_contents = $this->nodeStorage->loadMultiple(array_keys($child_contents));
      }
    }

    foreach ($child_contents as $weight => $child) {

      // Don't add removed child content from the table.
      if (!empty($form['removed_children']['#value'])) {
        if (in_array($child->id(), explode(',', (string) $form['removed_children']['#value']), TRUE)) {
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

      if (method_exists($child, 'isPublished') && !$child->isPublished() && $child->hasField('moderation_state')) {
        $state = $child->get('moderation_state')->getString();
        $form['child_content'][$child_nid]['title']['#suffix'] =
          ' <span title="Moderation status" class="moderation-state--' . str_replace('_', '-', $state) . '">' .
          ucfirst(str_replace('_', ' ', $state)) .
          '</span>';
      }

      $form['child_content'][$child_nid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $child->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => [
          'class' => ['table-sort-weight'],
        ],
      ];

      if ($child->bundle() === 'subtopic') {
        $form['child_content'][$child_nid]['edit'] = [
          '#type' => 'link',
          '#title' => $this->t('Edit'),
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $child->id()]),
          '#attributes' => [
            'class' => ['button--danger', 'link', 'button'],
            'title' => $this->t('Edit this subtopic to assign a new parent topic'),
            'target' => '_blank',
          ],
          '#wrapper_attributes' => [
            'class' => ['manage-topic-content-remove-cell'],
            'title' => $this->t('Edit this subtopic to assign a new parent topic.'),
          ],
        ];
      }
      else {
        $form['child_content'][$child_nid]['delete'] = [
          '#type' => 'submit',
          '#title' => $this->t('Remove'),
          '#name' => 'delete_' . $child_nid,
          '#value' => $this->t('Remove'),
          '#submit' => ['::ajaxSubmit'],
          '#ajax' => [
            'callback' => '::childContentCallback',
            'wrapper' => 'form-wrapper',
          ],
          '#attributes' => [
            'class' => ['button--danger', 'link'],
          ],
          '#wrapper_attributes' => [
            'class' => ['manage-topic-content-remove-cell'],
            'title' => $this->t('Remove this content from the topic.'),
          ],
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => [
        'class' => ['button--primary'],
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancel'],
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['manage-topic-content-cancel', 'button--danger', 'use-ajax'],
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
  public function childContentCallback(array &$form, FormStateInterface $form_state): array {
    // Remove Linkit entry after adding new content.
    $form['add_existing']['add_path']['#value'] = $form_state->getValue('add_path');
    return $form;
  }

  /**
   * Ajax callback to close the modal.
   */
  public function closeModalAjax(): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $parents = $form_state->getTriggeringElement()['#parents'];

    // Append call.
    if (($parents[0] ?? '') === 'add') {
      $add_path = (string) $form_state->getValue('add_path');

      if ($add_path === '') {
        $form_state->setErrorByName('add_path', $this->t('You must provide a URL.'));
        return;
      }

      // We only want valid url paths and not the typed text.
      if (!str_starts_with($add_path, '/node/')) {
        $form_state->setErrorByName('add_path', $this->t('Path must be a valid URL'));
        return;
      }

      $node_id = $this->extractNodeIdFromUrl($add_path);
      $node = $this->nodeStorage->load($node_id);

      if ($node) {
        if (count($this->topicManager->getParentNodes($node_id)) > TopicManager::maximumTopicsForType($node->bundle())) {
          $form_state->setErrorByName(
            'add_path',
            $this->t('This @type has the maximum number of topics assigned and cannot be added to this content.', ['@type' => $node->bundle()])
          );
        }
      }

      $child_content = $form_state->getValue('child_content');
      $new_content_nid = $node_id;

      if (is_array($child_content) && array_key_exists($new_content_nid, $child_content)) {
        $form_state->setErrorByName('add_path', $this->t('Child content entry already exists for this URL.'));
        return;
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): void {
    $parents = $form_state->getTriggeringElement()['#parents'];
    $removed_children = (string) $form_state->getValue('removed_children');

    // Append call.
    if (($parents[0] ?? '') === 'add') {
      $add_path = (string) $form_state->getValue('add_path');

      $child_content = $form_state->getValue('child_content') ?: [];
      $new_content_nid = $this->extractNodeIdFromUrl($add_path);

      $weight = 0;
      if (is_array($child_content) && !empty($child_content)) {
        $weight = $child_content[array_key_last($child_content)]['weight'] + 1;
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
      $removed = $removed_children !== '' ? explode(',', $removed_children) : [];
      $removed[] = $parents[1];

      $form_state->setValue('removed_children', implode(',', array_unique($removed)));
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $child_content = $form_state->getValue('child_content');
    $topic_nid = (int) $form_state->getValue('topic_nid');

    $topic = $this->nodeStorage->load($topic_nid);

    if (!$topic) {
      $this->messenger()->addError($this->t('Topic could not be loaded.'));
      return;
    }

    // TODO: Do a diff on the arrays and only update the field if different.
    $field_topic_content_updated = [];

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
  public function cancel(array $form, FormStateInterface $form_state): void {
    $form_state->setRedirect('entity.node.canonical', ['node' => $form_state->getValue('topic_nid')]);
  }

  /**
   * Return the node ID for the given path.
   */
  protected function extractNodeIdFromUrl(string $url): int {
    // Strip the host and match the alias to a node id.
    if (UrlHelper::isExternal($url)) {
      $host = $this->getRequest()->getSchemeAndHttpHost();
      $alias = substr($url, strlen($host));
      $path = $this->aliasManager->getPathByAlias($alias);
      return (int) substr($path, 6);
    }

    // Canonical URL. Trim to extract the node id parameter.
    return (int) substr($url, 6);
  }

  /**
   * True if the node has parents, otherwise false.
   */
  protected function subtopicHasParent(int $nid): bool {
    $node = $this->nodeStorage->load($nid);
    if ($node && $node->bundle() === 'subtopic') {
      return !empty($this->topicManager->getParentNodes($node));
    }
    return FALSE;
  }

}
