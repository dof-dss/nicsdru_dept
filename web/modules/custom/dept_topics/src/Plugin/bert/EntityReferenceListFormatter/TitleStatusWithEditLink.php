<?php

namespace Drupal\dept_topics\Plugin\bert\EntityReferenceListFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Render\Markup;
use Drupal\bert\EntityReferenceListFormatterPluginBase;

/**
 * Displays the entity label and moderation status linking to the edit form.
 *
 * @EntityReferenceListFormatter(
 *   id = "title_modstatus_with_edit_link",
 *   label = @Translation("Entity title and moderation status with edit link"),
 * )
 */
class TitleStatusWithEditLink extends EntityReferenceListFormatterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getCells(EntityInterface $entity): array {
    $entity = $this->entityRepository->getTranslationFromContext($entity);
    // @phpstan-ignore-next-line
    $state = $entity->get('moderation_state')->getString();
    $state_markup = ($state === 'published') ? '' : ucfirst($state);

    try {
      return [
        [
          '#type' => 'link',
          '#title' => Markup::create($entity->label()),
          '#url' => $entity->toUrl('edit-form'),
          '#attributes' => [
            'target' => '_blank',
            'rel' => 'noreferrer noopener',
          ],
        ],
        ['#markup' => $state_markup],
      ];
    }
    catch (UndefinedLinkTemplateException $e) {
      return [
        ['#markup' => Markup::create($entity->label())],
        ['#markup' => $state_markup],
      ];
    }
  }

}
