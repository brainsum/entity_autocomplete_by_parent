<?php

namespace Drupal\entity_autocomplete_by_parent\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;

/**
 * Defines the 'parent_entity_reference' entity field type.
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - target_type: The entity type to reference. Required.
 *
 * @FieldType(
 *   id = "parent_entity_reference",
 *   label = @Translation("Parent Entity reference"),
 *   description = @Translation("An entity field containing an parent entity reference."),
 *   category = @Translation("Reference"),
 *   default_widget = "entity_reference_autocomplete",
 *   default_formatter = "entity_reference_label",
 *   list_class = "\Drupal\entity_autocomplete_by_parent\ParentEntityReferenceFieldItemList",
 * )
 */
class ParentEntityReferenceItem extends EntityReferenceItem {
  // Class just for list class overridden.
}
