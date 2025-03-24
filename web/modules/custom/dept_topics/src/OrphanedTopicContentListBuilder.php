<?php

declare(strict_types=1);

namespace Drupal\dept_topics;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a list controller for the orphaned topic content entity type.
 */
final class OrphanedTopicContentListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['former_parent'] = $this->t('Former parent');
    $header['uid'] = $this->t('Orphaned by');
    $header['orphaned'] = $this->t('Orphaned on');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\dept_topics\OrphanedTopicContentInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = Link::createFromRoute($entity->label(), 'entity.node.canonical', ['node' => $entity->get('orphan')->getString()])->toString();

    $former_parent_id =  $entity->get('former_parent')->getString();


    if (!empty($former_parent_id)) {
      $former_parent = \Drupal::entityTypeManager()->getStorage('node')->load($former_parent_id);

      if (!empty($former_parent)) {
        $row['former_parent'] = Link::createFromRoute($former_parent->label(), 'entity.node.canonical', ['node' => $former_parent->id()])->toString();
      } else {
        $row['former_parent'] = $former_parent_id . ' (not found)';
      }
    }

    $username_options = [
      'label' => 'hidden',
      'settings' => ['link' => $entity->get('uid')->entity->isAuthenticated()],
    ];
    $row['uid']['data'] = $entity->get('uid')->view($username_options);
    $row['orphaned']['data'] = $entity->get('created')->view(['label' => 'hidden']);
    $row['operations']['data'] =  [
      '#type' => 'operations',
      '#links' => [
        'edit' => [
          'title' => $this->t('Edit orphan'),
          'url' => Url::fromRoute('entity.node.canonical', ['node' => $entity->get('orphan')->getString()]),
        ],
        'delete' => [
          'title' => $this->t('Delete orphan'),
          'url' => Url::fromRoute('entity.node.delete_form', ['node' => $entity->get('orphan')->getString()]),
          'attributes' => [
            'data-dialog-type' => 'modal',
            'class' => ['use-ajax'],
            'data-dialog-options' => Json::encode([
              'width' => '80%',
            ]),
          ]
        ]
      ],
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
    ];

    return $row;
  }

}
