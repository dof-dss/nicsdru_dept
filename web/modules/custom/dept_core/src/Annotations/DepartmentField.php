<?php

namespace Drupal\dept_core\Annotations;

/**
 * Marks Department entity field helper methods.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class DepartmentField {

  /**
   * Field label.
   *
   * @var string
   */
  private string $label;

  /**
   * Constructs a DepartmentField attribute.
   *
   * @param string $label
   *   Field label.
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
  public function label(): string {
    return ucfirst(trim($this->label));
  }

}
