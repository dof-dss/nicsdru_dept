<?php

namespace Drupal\dept_migrate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MigrateIndexFilterForm extends FormBase {

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
    return 'migrate_index_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form filter.
    $form['filter_type'] = [
      '#title' => $this->t('Filter type'),
      '#type' => 'select',
      '#options' => [
        'type' => 'Content type',
        'nid' => 'Node ID',
        'uuid' => 'UUID',
        'title' => 'Title',
        'd7nid' => 'Drupal 7 Node ID',
        'd7uuid' => 'Drupal 7 UUID',
        'd7title' => 'Drupal 7 Title',
      ],
      '#default_value' => $this->request->getCurrentRequest()->query->get('filter_type'),
    ];
    $form['filter_value'] = [
      '#title' => $this->t('Filter value'),
      '#type' => 'textfield',
      '#default_value' => $this->request->getCurrentRequest()->query->get('filter_value'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    if (!empty($form_values['filter_type'])) {
      $filter_value = $form_values['filter_value'] ?? '';

      $form_state->setRedirect('dept_migrate.default', [], [
        'query' => [
          'filter_type' => $form_values['filter_type'],
          'filter_value' => $filter_value,
        ],
      ]);
    }
  }

}
