<?php

declare(strict_types = 1);

namespace Drupal\dept_topics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dept_topics\TopicManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Constructs a ModerationStateChangeSubscriber object.
   *
   * @param \Drupal\dept_topics\TopicManager $topic_manager
   *   The Topic Manager service.
   */
  public function __construct(TopicManager $topic_manager) {
    $this->topicManager = $topic_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('topic.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dept_topics_add_existing_content';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $nid = $this->getRequest()->query->get('nid');
    $types = $this->topicManager->getTopicChildNodeTypes();

    $form['content'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Content'),
      '#description' => $this->t('Begin typing the title of the content.'),
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'topic_child_content',
      ],
    ];

    $form['topic_nid'] = [
      '#type' => 'hidden',
      '#value' => $nid
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Add'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
   $values = $form_state->getValues();
  }

}
