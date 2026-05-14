<?php

namespace Drupal\dept_fs;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\media\MediaInterface;

enum Table {
  case Base;
  case Revision;
}

readonly class ConsolidationStore {

  public function __construct(
    public ContentEntityInterface $mediaHost,
    public array $usageData,
    public MediaInterface $currentMedia,
    public MediaInterface $replacementMedia,
  ){}

  public function table(Table $type) {
    if ($type == Table::Base) {
      return $table = $this->mediaHost->getEntityTypeId() . "__" . $this->field();
    }
    else {
      return $table = $this->mediaHost->getEntityTypeId() . "_revision__" . $this->field();
    }
  }

  public function field() {
    return $this->usageData['field_name'];
  }

  public function relationshipType() {
    return $this->usageData['method'];
  }

}
