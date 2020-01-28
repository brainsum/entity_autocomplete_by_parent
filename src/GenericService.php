<?php

namespace Drupal\entity_autocomplete_by_parent;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Class GenericService.
 */
class GenericService implements ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * The Drupal\Core\Entity\EntityInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The Drupal\Core\Form\FormStateInterface definition.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * From module implements hook_field_widget_form_alter().
   *
   * @param array $element
   *   The widget element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $context
   *   The context array.
   */
  public function fieldWidgetFormAlter(array &$element,
    FormStateInterface $form_state,
    array $context) {
    /** @var \Drupal\Core\Field\WidgetBaseInterface $widget */
    $widget = $context['widget'];
    $widget_definition = $widget->getPluginDefinition();
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $field */
    $field = $context['items'];
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $field->getFieldDefinition();
    $settings = $field_definition->getSettings();
    if (isset($widget_definition['id']) && isset($settings['handler']) &&
      $settings['handler'] == 'parent_field_reference' &&
      isset($settings['handler_settings'])) {
      if ($form_state->getformObject()) {
        switch ($widget_definition['id']) {
          case 'entity_reference_autocomplete':
          case 'entity_reference_autocomplete_tags':
            $field_definition->addConstraint('ValidParentReference', [
              'parents' => $this->getParents($settings['handler_settings'], $form_state),
            ]);
            $element['target_id']['#type'] = 'entity_autocomplete_by_parent';
            break;

          // @todo: other widge types change.
        }
      }
    }
  }

  /**
   * From module implements hook_form_alter().
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   * @param array $storage
   *   The form storage array.
   *
   * @return mixed
   *   The form array or nothing.
   */
  public function formAlter(array &$form,
    FormStateInterface $form_state,
    array $storage) {
    $form_state->disableCache();
    $this->formState = $form_state;
    $this->entity = $form_state->getformObject()->getEntity();
    /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $form_display */
    $form_display = $storage['form_display'];
    $form['entity_autocomplete_by_parent'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['id' => 'entity-autocomplete-by-parent'],
    ];
    foreach (Element::children($form) as $key) {
      if (!empty($form_display->getRenderer($key))) {
        /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
        if ($items = $this->entity->get($key)) {
          $settings = $items->getFieldDefinition()->getSettings();
        }
        if (!empty($settings['handler']) &&
          $settings['handler'] === 'parent_field_reference' &&
          !empty($settings['handler_settings']['view']['parent'])) {
          // Store autocomplete by parent field name and class to form state.
          if (empty($form[$key]['#attributes']['class'])) {
            $form[$key]['#attributes']['class'][] =
              str_replace('_', '-', $key) . '-parent-field-reference';
          }
          $storage['parent_field_reference_fields'][$key] =
            implode('.', $form[$key]['#attributes']['class']);
          $field_name = $settings['handler_settings']['view']['parent'];
          $form_state->setStorage($storage);
          if (isset($form[$field_name]) && is_array($form[$field_name])) {
            // Prepare ajax callback to parent element.
            $element = &$form[$field_name];
            $this->setParentElementAjax($element, $form);
          }
        }
      }
    }
  }

  /**
   * Set ajax for parent form element.
   *
   * @param array $element
   *   The modified element.
   * @param array $form
   *   The form array.
   */
  protected function setParentElementAjax(array &$element,
    array $form) {
    $names = Element::children($element);
    if (empty($names)) {
      if (empty($element['#ajax'])) {
        $element['#ajax'] = [
          'callback' => [$this, 'entityAutocompleteByParentAjaxCallback'],
          'event' => 'change',
        ];
      }
    }
    else {
      $key = reset($names);
      $target = &$element[$key];
      $this->setParentElementAjax($target, $form);
    }
  }

  /**
   * Get parents dynamicaly.
   *
   * @param array $settings
   *   The field settings array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   * @param array $complete_form
   *   The complete form array.
   *
   * @return array
   *   The arguments array.
   */
  public function getParents(array $settings,
    FormStateInterface $form_state,
    array $complete_form = []): array {
    $parents = [];
    if (isset($settings['view']['parent'])) {
      $arguments = explode(',', $settings['view']['parent']);
      foreach ($arguments as $field_name) {
        if (!empty($form_state->getUserInput()[$field_name])) {
          $parents[] = [$field_name => $form_state->getUserInput()[$field_name]];
        }
        elseif ($value = $form_state->getValue($field_name)) {
          $parents[] = [
            $field_name => is_array($value) ? \reset($value) : $value,
          ];
        }
        else {
          $parents[] = [$field_name => 'all'];
        }
      }
    }
    // Allow other modules to alter the $data invoking:
    // hook_entity_autocomplete_by_parent_arg_alter().
    $context = [
      'settings' => $settings,
      'form_state' => $form_state,
      'form' => $complete_form,
    ];
    $this->container->get('module_handler')
      ->alter('entity_autocomplete_by_parent_arg', $parents, $context);

    $arguments = [];
    foreach ($parents as $values) {
      $arguments[] = \reset($values);
    }

    return $arguments;
  }

  /**
   * Autocomplete by parent field ajax callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public static function entityAutocompleteByParentAjaxCallback(array &$form,
    FormStateInterface $form_state) {
    // Prepare ajax response from form state storage parent reference fields.
    $storage = $form_state->getStorage();
    $response = new AjaxResponse();
    if (isset($storage['parent_field_reference_fields'])) {
      foreach ($storage['parent_field_reference_fields'] as $field_name => $class) {
        if (!empty($class)) {
          if (isset($form[$field_name]['#groups']['content']['#group_exists'])) {
            unset($form[$field_name]['#groups']['content']['#group_exists']);
          }
          $response->addCommand(new ReplaceCommand('.' . $class, ($form[$field_name])));
        }
      }
    }

    return $response;
  }

}
