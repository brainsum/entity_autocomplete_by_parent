<?php

namespace Drupal\entity_autocomplete_by_parent\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;

/**
 * Entity Parent Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid children of parent.
 *
 * @Constraint(
 *   id = "ValidParentReference",
 *   label = @Translation("Entity Reference valid parent reference", context = "Validation")
 * )
 */
class ValidParentReferenceConstraint extends ValidReferenceConstraint {

  /**
   * The parents definition.
   *
   * @var array
   */
  public $parents;

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption() {
    return 'parents';
  }

}
