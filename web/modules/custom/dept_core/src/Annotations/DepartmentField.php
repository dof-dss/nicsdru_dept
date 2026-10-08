<?php

namespace Drupal\dept_core\Annotations;

/**
 * Marks Department entity field helper methods for block configuration.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class DepartmentField {

  /**
   * Field label.
   *
   * @var string
   */
  private readonly string $label;

  /**
   * Constructs a DepartmentField attribute.
   */
  public function __construct(string $label) {
    $this->label = $label;
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
