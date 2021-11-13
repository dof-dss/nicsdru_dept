<?php

namespace Drupal\dept_example_group_entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the departmental example group content entity entity edit forms.
 */
class DeptExampleGroupEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New departmental example group content entity %label has been created.', $message_arguments));
      $this->logger('dept_example_group_entity')->notice('Created new departmental example group content entity %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The departmental example group content entity %label has been updated.', $message_arguments));
      $this->logger('dept_example_group_entity')->notice('Updated new departmental example group content entity %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.dept_example_group_entity.canonical', ['dept_example_group_entity' => $entity->id()]);
  }

}
