<?php

declare(strict_types=1);

namespace Drupal\revision_delete_tools\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a revisions deletion confirmation form.
 */
final class BulkDeleteConfirmForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'revision_delete_tools_bulk_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete these revisions?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    $node = \Drupal::routeMatch()->getParameter('node');
    return $node->toUrl('version-history');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface|null $node = NULL, string|null $action = NULL) {
    $form = parent::buildForm($form, $form_state);
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    if (!in_array($action, ['older', 'newer'])) {
      return [
        '#markup' => $this->t('Invalid bulk delete action. Request must be either "older" or "newer".'),
      ];
    }

    $current_vid = $node->getRevisionId();
    $all_vids = $node_storage->revisionIds($node);
    $selected_vids = [];

    foreach ($all_vids as $vid) {
      if (($action == 'newer' && $vid > $current_vid) || ($action == 'older' && $vid < $current_vid)) {
        $selected_vids[] = $vid;
      }
    }

    if ((count($selected_vids) < 1)) {
      $this->messenger()->addStatus($this->t('There are no revisions to delete for that action.'));
      return new RedirectResponse($node->toUrl('version-history')->toUriString());
    }

    $form_state->set('vids', $selected_vids);
    $form_state->set('redirect', $node->toUrl('version-history')->toUriString());

    $form['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t("Are you sure you want to delete %count revision(s) for the @bundle '%title'?", [
        '%count' => count($selected_vids),
        '@bundle' => $node->bundle(),
        '%title' => $node->label()
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $vids = $form_state->get('vids');

    foreach ($vids as $vid) {
      $node_storage->deleteRevision($vid);
    }

    $this->messenger()->addStatus($this->t('Deleted @count revision(s) successfully.', ['@count' => count($vids)]));
    $form_state->setRedirectUrl(Url::fromUri($form_state->get('redirect')));
  }

}
