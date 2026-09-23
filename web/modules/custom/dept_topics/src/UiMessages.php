<?php

namespace Drupal\dept_topics;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Centralised UI message helpers for consistent messaging across Topics.
 */
class UiMessages {

  /**
   * Message shown after the Linkit profile is updated.
   */
  public static function linkitUpdate(): TranslatableMarkup {
    return t('Linkit profile (topic_child_content) updated, please ensure you export this configuration.');
  }

  /**
   * Message for when a user tries to assign a subtopic to itself.
   */
  public static function subtopicCannotReferenceItself(): TranslatableMarkup {
    return t('A subtopic cannot reference itself. Please select a different topic or subtopic.');
  }

  /**
   * Message for when a subtopic already has a parent topic assigned.
   */
  public static function subtopicHasParent(): TranslatableMarkup {
    return t('This subtopic is already assigned to a parent topic and cannot be linked to multiple parent topics.');
  }

  /**
   * Message for a child node that is assigned to an unpublished topic.
   *
   * @param string $topic_title
   *   The topic title.
   */
  public static function assignedToUnpublishedTopic(string $topic_title): TranslatableMarkup {
    return t('This content is associated with an unpublished topic (%topic), so visitors have no way to reach it through that topic on the site.', [
      '%topic' => $topic_title,
    ]);
  }

  /**
   * Message for published content whose topics differ from that revision.
   */
  public static function topicChangesPendingPublish(): TranslatableMarkup {
    return t("This content already has a published revision, and the Topics you've selected differ from that published version. The new Topics will not take effect until this revision is published.");
  }

  /**
   * Message on the disabled delete button when a topic has active children.
   */
  public static function deleteButtonBlockedActiveChildren(): TranslatableMarkup {
    return t('This content has active child pages. It cannot be deleted until child pages have been reallocated to a different topic, archived or deleted.');
  }

  /**
   * Validation error when archiving a topic that has active children.
   */
  public static function archiveBlockedActiveChildren(): TranslatableMarkup {
    return t('This content has active child pages. It cannot be archived until child pages have been reallocated to a different topic, archived or deleted.');
  }

  /**
   * Notice shown when reverting a revision is blocked by active children.
   *
   * @param string $bundle
   *   The node bundle.
   */
  public static function revertBlockedActiveChildren(string $bundle): TranslatableMarkup {
    return t('This @bundle has active child pages. It cannot be reverted to an archived state until child pages have been reallocated to a different topic, archived or deleted.', [
      '@bundle' => $bundle,
    ]);
  }

  /**
   * Notice/exception shown when deletion is blocked by active children.
   *
   * @param string $bundle
   *   The node bundle.
   * @param string $title
   *   The node title.
   */
  public static function deleteBlockedActiveChildren(string $bundle, string $title): TranslatableMarkup {
    return t("This @bundle '%title' cannot be deleted until all child pages have been reallocated to a different topic, archived or deleted.", [
      '@bundle' => $bundle,
      '%title' => $title,
    ]);
  }

}
