<?php

namespace Drupal\dept_topics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides form response for the child content order modal.
 */
class ChildOrderFormController extends ControllerBase {

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder service.
   */
  public function __construct(EntityFormBuilderInterface $entity_form_builder) {
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.form_builder')
    );
  }

  /**
   * Builds the response.
   */
  public function build($node) {

    if ($node->bundle() !== 'subtopic') {
      return [
        '#markup' => $this->t('This type of bundle (%bundle) cannot have child content reordered.', [
          '%bundle' => $node->bundle(),
        ])
      ];
    }

    // Fetch the node edit form with the Form mode 'child_order'.
    $form = $this->entityFormBuilder->getForm($node, 'child_order');

    unset($form['advanced']);

    unset($form["actions"]["preview"]);
    unset($form["actions"]["delete"]);

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('cancel'),
      '#weight' => 10,
      '#attributes' => ['class' => ['child-order-cancel']],
    ];

    $form['#attached']['library'][] = 'dept_topics/child_order';

    return $form;
  }

}
