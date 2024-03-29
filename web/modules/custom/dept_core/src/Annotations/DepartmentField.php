<?php

namespace Drupal\dept_core\Annotations;

/**
 * Provides an annotation to Department entity field helper methods.
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class DepartmentField {

  /**
   * Field label.
   *
   * @var string
   */
  private string $label;

  /**
   * Annotation contructor.
   *
   * @param array $values
   *   Annotation values.
   */
  public function __construct(array $values) {
    if (array_key_exists('label', $values)) {
      $this->label = $values['label'];
    }
  }

  /**
   * Field label.
   *
   * @return string
   *   Returns a formatted field label.
   */
  public function label() {
    return ucfirst(trim($this->label));
  }

}
