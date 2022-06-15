<?php

namespace Drupal\dept_migrate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class NodeDetailFilterForm extends FormBase {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $request;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->request = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_detail_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form filter.
    $form['nid'] = [
      '#title' => $this->t('Drupal 9 Node ID'),
      '#type' => 'textfield',
      '#default_value' => $this->request->getCurrentRequest()->query->get('nid'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Lookup'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    if (!empty($form_values['nid'])) {
      $nid = $form_values['nid'] ?? '';
      $form_state->setRedirect('dept_migrate.detail', [], [
        'query' => [
          'nid' => $nid,
        ],
      ]);
    }
  }

}
