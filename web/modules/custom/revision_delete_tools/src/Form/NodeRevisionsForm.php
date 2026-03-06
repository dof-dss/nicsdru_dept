<?php

namespace Drupal\revision_delete_tools\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for revision overview page.
 */
class NodeRevisionsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The date service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $date;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a Revision Overview Form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date
   *   The date service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    DateFormatterInterface $date,
    RendererInterface $renderer,
    LanguageManagerInterface $language_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->date = $date;
    $this->renderer = $renderer;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'revision_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    /*
     * Most of the code below is lifted and modified from the excellent
     * Diff module (https://www.drupal.org/project/diff) which does a nice
     * job of changing the Core controller output to a form render array.
     */
    $account = $this->currentUser;
    $langcode = $node->language()->getId();
    $langname = $node->language()->getName();
    $languages = $node->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $node_storage = $this->entityTypeManager->getStorage('node');
    $type = $node->getType();

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition($node->getEntityType()->getKey('id'), $node->id())
      ->pager(25)
      ->allRevisions()
      ->sort($node->getEntityType()->getKey('revision'), 'DESC')
      // Access to the content has already been verified. Disable query-level
      // access checking so that revisions for unpublished content still
      // appear.
      ->accessCheck(FALSE)
      ->execute();
    $vids = array_keys($query);

    $build['#title'] = $has_translations ? $this->t('@langname REVISIONS for %title', [
      '@langname' => $langname,
      '%title' => $node->label(),
    ]) : $this->t('Revisions for %title', [
      '%title' => $node->label(),
    ]);
    $build['nid'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    $rev_revert_perm = $account->hasPermission("revert $type revisions") ||
      $account->hasPermission('revert all revisions') ||
      $account->hasPermission('administer nodes');
    $rev_delete_perm = $account->hasPermission("delete $type revisions") ||
      $account->hasPermission('delete all revisions') ||
      $account->hasPermission('administer nodes');
    $revert_permission = $rev_revert_perm && $node->access('update');
    $delete_permission = $rev_delete_perm && $node->access('delete');

    $build['node_revisions_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Revision'),
        $this->t('Operations'),
      ],
      '#attributes' => ['class' => ['node-revision-table']],
    ];

    $default_revision = $node->getRevisionId();

    foreach ($vids as $key => $vid) {
      $previous_revision = NULL;
      if (isset($vids[$key + 1])) {
        $previous_revision = $node_storage->loadRevision($vids[$key + 1]);
      }
      /** @var \Drupal\Core\Entity\ContentEntityInterface $revision */
      if ($revision = $node_storage->loadRevision($vid)) {
        if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
          $username = [
            '#theme' => 'username',
            '#account' => $revision->getRevisionUser(),
          ];
          $revision_date = $this->date->format($revision->getRevisionCreationTime(), 'short');
          // Use revision link to link to revisions that are not active.
          if ($vid != $node->getRevisionId()) {
            $link = Link::fromTextAndUrl($revision_date, new Url('entity.node.revision', [
              'node' => $node->id(),
              'node_revision' => $vid
            ]));
          }
          else {
            $link = $node->toLink($revision_date);
          }

          if ($vid == $default_revision) {
            $row = [
              'revision' => $this->buildRevision($link, $username, $revision, $previous_revision),
            ];

            $row['operations'] = [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
              '#attributes' => [
                'class' => ['revision-current'],
              ],
            ];
            $row['#attributes'] = [
              'class' => ['revision-current'],
            ];
          }
          else {
            $route_params = [
              'node' => $node->id(),
              'node_revision' => $vid,
              'langcode' => $langcode,
            ];
            $links = [];
            if ($revert_permission) {
              $links['revert'] = [
                'title' => $vid < $node->getRevisionId() ? $this->t('Revert') : $this->t('Set as current revision'),
                'url' => $has_translations ?
                Url::fromRoute('node.revision_revert_translation_confirm', [
                  'node' => $node->id(),
                  'node_revision' => $vid,
                  'langcode' => $langcode
                ]) :
                Url::fromRoute('node.revision_revert_confirm', [
                  'node' => $node->id(),
                  'node_revision' => $vid
                ]),
              ];
            }
            if ($delete_permission) {
              $links['delete'] = [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('node.revision_delete_confirm', $route_params),
              ];
            }

            // Here we don't have to deal with 'only one revision' case because
            // if there's only one revision it will also be the default one,
            // entering on the first branch of this if else statement.
            $row = [
              'revision' => $this->buildRevision($link, $username, $revision, $previous_revision),
              'operations' => [
                '#type' => 'operations',
                '#links' => $links,
              ],
            ];
          }
          // Add the row to the table.
          $build['node_revisions_table'][] = $row;
        }
      }
    }

    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

  /**
   * Set and return configuration for revision.
   *
   * @param \Drupal\Core\Link $link
   *   Link attribute.
   * @param string $username
   *   Username attribute.
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   Revision parameter for getRevisionDescription function.
   * @param \Drupal\Core\Entity\ContentEntityInterface $previous_revision
   *   (optional) Previous revision for getRevisionDescription function.
   *   Defaults to NULL.
   *
   * @return array
   *   Configuration for revision.
   */
  protected function buildRevision(Link $link, $username, ContentEntityInterface $revision, ?ContentEntityInterface $previous_revision = NULL) {
    return [
      '#type' => 'inline_template',
      '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
      '#context' => [
        'date' => $link->toString(),
        // @see https://www.drupal.org/node/3407994
        // Added a suggested method renderInIsolation().
        // @phpstan-ignore-next-line
        'username' => version_compare(\Drupal::VERSION, '10.3', '<') ? $this->renderer->renderPlain($username) : $this->renderer->renderInIsolation($username),
      ],
    ];
  }

  /**
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
