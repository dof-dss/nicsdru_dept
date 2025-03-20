<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the 'dept_topics_topic_contents' field widget.
 *
 * @FieldWidget(
 *   id = "dept_topics_topic_contents",
 *   label = @Translation("Topic Contents"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
final class TopicContentsWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $setting = ['foo' => 'bar'];
    return $setting + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['foo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Foo'),
      '#default_value' => $this->getSetting('foo'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Foo: @foo', ['@foo' => $this->getSetting('foo')]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {

    $arr_element = parent::formElement($items, $delta, $element, $form, $form_state);
    $rows = [];
    $nodes = $items->referencedEntities();

    $header = [
      'node' => $this->t('Content'),
      'actions' => $this->t('Actions'),
    ];


    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,

    ];

    foreach ($nodes as $delta => $node) {
      $build['table'][$delta]['node'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => ['target_bundles' => ['subtopic', 'application', 'article', 'publication']],
        '#validate_reference' => FALSE,
        '#default_value' => $node,
      ];

      if ($node->bundle() == 'subtopic') {
        $build['table'][$delta]['actions'] = [
          '#type' => 'link',
          '#title' => 'Edit',
          '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()])
        ];
      }
      else {
        $build['table'][$delta]['actions'] = [
          '#type' => 'link',
          '#title' => 'Delete',
          '#url' => Url::fromRoute('entity.node.delete_form', ['node' => $node->id()])
        ];
      }
    }

    $element['value'] = $build['table'];
    return $element;
  }

}
