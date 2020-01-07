<?php

namespace Drupal\entity_autocomplete_by_parent;

use Drupal\Core\Field\EntityReferenceFieldItemList;

/**
 * Defines a item list class for entity reference fields.
 */
class ParentEntityReferenceFieldItemList extends EntityReferenceFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    /** @var \Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint $constrain */
    foreach ($constraints as $key => $constrain) {
      $class = $constrain->validatedBy();
      if ($class === 'Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraintValidator') {
        unset($constraints[$key]);
      }
    }
    return $constraints;
  }

}
