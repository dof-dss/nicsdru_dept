<?php

namespace Drupal\dept_fs;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\media\MediaInterface;

/**
 * DTO for Media consolidation processes.
 */
readonly class ConsolidationStore {

  public function __construct(
    public ContentEntityInterface $mediaHost,
    public array $usageData,
    public MediaInterface $currentMedia,
    public MediaInterface $replacementMedia,
  ) {

  }

  /**
   * The table name.
   *
   * @param ConsolidationTable $type
   *   The table type, base or revision.
   */
  public function table(ConsolidationTable $type):string {
    if ($type == ConsolidationTable::Base) {
      return $table = $this->mediaHost->getEntityTypeId() . "__" . $this->field();
    }
    else {
      return $table = $this->mediaHost->getEntityTypeId() . "_revision__" . $this->field();
    }
  }

  /**
   * The Entity Usage field.
   */
  public function field():string {
    return $this->usageData['field_name'];
  }

  /**
   * The Entity Usage method.
   */
  public function relationshipType(): string {
    return $this->usageData['method'];
  }

}
