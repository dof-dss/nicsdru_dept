<?php

namespace Drupal\dept_node\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for altering a node path alias.
 */
final class UpdatePathAliasForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dept_node_update_path_alias';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $nid
   *   The node id of the path alias.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = ''): array {
    $source = '/node/' . $nid;

    // Fetch existing alias for this node path.
    $results = $this->database
      ->query(
        "SELECT pa.alias FROM {path_alias} pa WHERE pa.path = :source",
        [':source' => $source]
      )
      ->fetchCol();

    $form['#prefix'] = '<div id="update-path-alias-form">';
    $form['#suffix'] = '</div>';

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['new_alias'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path alias'),
      '#default_value' => $results[0] ?? '',
      '#required' => TRUE,
    ];

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#attributes' => [
        'class' => ['use-ajax'],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#attributes' => [
        'class' => ['use-ajax'],
      ],
      '#ajax' => [
        'callback' => [$this, 'closeModalAjax'],
        'event' => 'click',
      ],
      // Prevent validation when cancelling.
      '#limit_validation_errors' => [],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * Ajax callback for form submission.
   */
  public function submitFormAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#update-path-alias-form', $form));
      return $response;
    }

    $nid = (string) $form_state->getValue('nid');
    $node_path = '/node/' . $nid;
    $new_alias = (string) $form_state->getValue('new_alias');

    $this->database->update('path_alias')
      ->fields(['alias' => $new_alias])
      ->condition('path', $node_path, '=')
      ->execute();

    $response->addCommand(new RedirectCommand(
      Url::fromRoute('entity.node.canonical', ['node' => $nid])->toString()
    ));

    return $response;
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
    $values = $form_state->getValues();
    $nid = (string) ($values['nid'] ?? '');
    $new_alias = (string) ($values['new_alias'] ?? '');

    $results = $this->database->query(
      "SELECT pa.alias FROM {path_alias} pa WHERE pa.alias = :alias AND pa.path <> :path",
      [
        ':alias' => $new_alias,
        ':path' => '/node/' . $nid,
      ]
    )->fetchCol();

    if (!empty($results)) {
      $form_state->setErrorByName('new_alias', $this->t('Alias already in use, please try another.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // No-op: AJAX submit handled by submitFormAjax().
  }

}
