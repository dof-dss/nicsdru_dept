<?php

namespace Drupal\dept_core\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\EntityField;

/**
 * A field that displays entity field data.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("fieldPlus")
 */
class EntityFieldPlus extends EntityField {

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['alter'] = [
      'contains' => [
        'rel_2_abs_link' => ['default' => FALSE],
      ],
    ];

    return $options;
  }

  /**
   * Default options form that provides the label widget that all fields
   * should have.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['alter']['rel_2_abs_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rewrite relative to absolute urls'),
      '#default_value' => $this->options['alter']['rel_2_abs_link'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function renderText($alter) {
    $render = parent::renderText($alter);

    if ($alter['rel_2_abs_link'] === 1) {
      $entity = $alter['raw']->getEntity();
    }
    return $render;
  }

}
