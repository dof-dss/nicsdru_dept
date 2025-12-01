<?php

declare(strict_types=1);

namespace Drupal\dept_topics\Plugin\Field\FieldFormatter;


use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Plugin implementation of the 'Topic Contents' formatter.
 *
 * @FieldFormatter(
 *   id = "dept_topics_topic_contents",
 *   label = @Translation("Topic contents"),
 *   field_types = {"entity_reference"},
 * )
 */
final class TopicContentsFormatter extends EntityReferenceEntityFormatter implements TrustedCallbackInterface {

  public static function trustedCallbacks() {
    return [
      'alterNodeRender',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $view_mode = $this->getSetting('view_mode');
    $elements = [];
    $topic = $items->getEntity();
    // TODO: Inject service.
    $moderation_service = \Drupal::service('content_moderation.moderation_information');
    $user_is_anonymous = \Drupal::currentUser()->isAnonymous();

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {

      $is_published = $moderation_service->isDefaultRevisionPublished($entity);

      if (!$is_published && $user_is_anonymous) {
        continue;
      };

      $child_current_topics = array_column($entity->get('field_site_topics')->getValue(), 'target_id');

      if ($user_is_anonymous && !in_array($topic->id(), $child_current_topics, TRUE)) {
        continue;
      }

      $moderation_states = [];

      if (!$user_is_anonymous) {
        $db = \Drupal::database();

        $query = $db->select('content_moderation_state_field_revision', 'modstate');
        $query->join('node_revision__field_site_topics', 'revtopics', 'revtopics.entity_id = modstate.content_entity_id AND revtopics.revision_id = modstate.content_entity_revision_id');
        $query->fields('modstate', ['moderation_state']);
        $query->condition('revtopics.entity_id', $entity->id());
        $query->condition('revtopics.field_site_topics_target_id', $topic->id());

        $moderation_states = $query->execute()->fetchCol();
      }

      // Due to render caching and delayed calls, the viewElements() method
      // will be called later in the rendering process through a '#pre_render'
      // callback, so we need to generate a counter that takes into account
      // all the relevant information about this field and the referenced
      // entity that is being rendered.
      $recursive_render_id = $items->getFieldDefinition()->getTargetEntityTypeId()
        . $items->getFieldDefinition()->getTargetBundle()
        . $items->getName()
        // We include the referencing entity, so we can render default images
        // without hitting recursive protections.
        . $items->getEntity()->id()
        . $entity->getEntityTypeId()
        . $entity->id();

      if (isset(static::$recursiveRenderDepth[$recursive_render_id])) {
        static::$recursiveRenderDepth[$recursive_render_id]++;
      }
      else {
        static::$recursiveRenderDepth[$recursive_render_id] = 1;
      }

      // Protect ourselves from recursive rendering.
      if (static::$recursiveRenderDepth[$recursive_render_id] > static::RECURSIVE_RENDER_LIMIT) {
        $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity %entity_type: %entity_id, using the %field_name field on the %parent_entity_type:%parent_bundle %parent_entity_id entity. Aborting rendering.', [
          '%entity_type' => $entity->getEntityTypeId(),
          '%entity_id' => $entity->id(),
          '%field_name' => $items->getName(),
          '%parent_entity_type' => $items->getFieldDefinition()->getTargetEntityTypeId(),
          '%parent_bundle' => $items->getFieldDefinition()->getTargetBundle(),
          '%parent_entity_id' => $items->getEntity()->id(),
        ]);
        return $elements;
      }

      $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
      $elements[$delta] = $view_builder->view($entity, $view_mode, $entity->language()->getId());

      if (!empty($moderation_states))  {
        $elements[$delta]['#moderation_states'] = $moderation_states;
        $elements[$delta]['#pre_render'][] = [get_class($this), 'alterNodeRender'];
      }


      // Add a resource attribute to set the mapping property's value to the
      // entity's URL. Since we don't know what the markup of the entity will
      // be, we shouldn't rely on it for structured data.
      if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
        $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
      }
    }

    return $elements;
  }

  public static function alterNodeRender(array $element) {

    if (\Drupal::currentUser()->isAuthenticated() && array_key_exists('#moderation_states', $element)) {
      foreach ($element['#moderation_states'] as $moderation_state) {
        $element['#attributes']['class'][] = 'ms-' . str_replace(' ', '-', $moderation_state); ;
      }
    }

    return $element;
  }

}
