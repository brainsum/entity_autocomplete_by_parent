<?php

namespace Drupal\entity_autocomplete_by_parent\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views\Plugin\EntityReferenceSelection\ViewsSelection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'selection' entity_reference.
 *
 * @EntityReferenceSelection(
 *   id = "parent_field_reference",
 *   label = @Translation("Views: Filter by an entity reference view with parent field reference argument"),
 *   group = "parent_field_reference",
 *   weight = 100
 * )
 */
class ViewsByParentFieldSelection extends ViewsSelection implements SelectionWithAutocreateInterface {

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  public $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    AccountInterface $current_user,
    RendererInterface $renderer = NULL,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition,
      $entity_type_manager, $module_handler, $current_user, $renderer);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    unset($configuration['view']['arguments']);
    $configuration['view'] += [
      'parent' => '',
    ];
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form,
    FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    $entity_type_id = $configuration['target_type'];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $bundle_options = [];
    foreach ($bundles as $bundle_name => $bundle_info) {
      $bundle_options[$bundle_name] = $bundle_info['label'];
    }
    natsort($bundle_options);

    $form = parent::buildConfigurationForm($form, $form_state);
    $view_settings = $configuration['view'];
    unset($form['view']['arguments']);
    $default = !empty($view_settings['parent']) ? $view_settings['parent'] : '';
    $form['view']['parent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Argument field name'),
      '#default_value' => $default,
      '#required' => FALSE,
      '#description' => $this->t('Provide the field name be contained the argument to pass to the view.'),
    ];

    $form['auto_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Create referenced entities if they don't already exist"),
      '#default_value' => $configuration['auto_create'],
    ];

    $form['auto_create_max'] = [
      '#type' => 'number',
      '#title' => $this->t('Max new items store per submit'),
      '#default_value' => $configuration['auto_create_max'],
      '#states' => [
        'visible' => [
          ':input[name="settings[handler_settings][auto_create]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['auto_create_ignore_text_cases'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Ignore text cases"),
      '#default_value' => $configuration['auto_create_ignore_text_cases'],
      '#description' => $this->t('Check if ignore same text different font cases. For example should not save text if already exist foo and enter Foo as new item.'),
      '#states' => [
        'visible' => [
          ':input[name="settings[handler_settings][auto_create]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    if ($entity_type->hasKey('bundle')) {
      $bundles = array_intersect_key($bundle_options, array_filter((array) $configuration['target_bundles']));
      $form['target_bundles'] = [
        '#type' => 'select',
        '#title' => $this->t('Store new items in'),
        '#options' => $bundle_options,
        '#default_value' => (array) $configuration['target_bundles'],
        '#access' => count($bundle_options) > 1,
        '#element_validate' => [[get_class($this), 'elementValidateFilter']],
        '#states' => [
          'visible' => [
            ':input[name="settings[handler_settings][auto_create]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids, $args = []) {
    $entities = $this->getDisplayExecutionResults(NULL, 'CONTAINS', 0, $ids, $args);
    $result = [];
    if ($entities) {
      $result = array_keys($entities);
    }
    return $result;
  }

  /**
   * Form element validation handler; Filters the #value property of an element.
   */
  public static function elementValidateFilter(&$element, FormStateInterface $form_state) {
    $user_input = $form_state->getUserInput();
    if (!empty($user_input['settings']['handler_settings']['target_bundles'])) {
      $element['#value'] = [$user_input['settings']['handler_settings']['target_bundles']];
    }
    $form_state->setValueForElement($element, $element['#value']);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0, $parent = []) {
    $entities = [];
    if ($display_execution_results = $this->getDisplayExecutionResults($match, $match_operator, $limit, [], $parent)) {
      $entities = $this->stripAdminAndAnchorTagsFromResults($display_execution_results);
    }
    return $entities;
  }

  /**
   * Fetches the results of executing the display.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   * @param int $limit
   *   Limit the query to a given number of items. Defaults to 0, which
   *   indicates no limiting.
   * @param array|null $ids
   *   Array of entity IDs. Defaults to NULL.
   * @param array $parent
   *   The parents array.
   *
   * @return array
   *   The results.
   */
  protected function getDisplayExecutionResults(string $match = NULL,
    string $match_operator = 'CONTAINS',
    int $limit = 0,
    array $ids = NULL,
    array $parent = []): array {
    $display_name = $this->getConfiguration()['view']['display_name'];
    $results = [];
    if ($this->initializeView($match, $match_operator, $limit, $ids)) {
      $results = $this->view->executeDisplay($display_name, $parent);
    }
    return $results ?? [];
  }

  /**
   * Element validate; Check View is valid.
   */
  public static function settingsFormValidate($element,
    FormStateInterface $form_state,
    $form) {
    // Split view name and display name from the 'view_and_display' value.
    if (!empty($element['view_and_display']['#value'])) {
      list($view, $display) = explode(':', $element['view_and_display']['#value']);
    }
    else {
      $form_state->setError($element, t('The views entity selection mode requires a view.'));
      return;
    }

    $value = [
      'view_name' => $view,
      'display_name' => $display,
      'parent' => trim($element['parent']['#value']),
      'auto_create' => $element['auto_create']['#value'],
      'auto_create_max' => $element['auto']['auto_create_max']['#value'],
      'auto_create_ignore_text_cases' => $element['auto']['auto_create_ignore_text_cases']['#value'],
      'target_bundles' => $element['auto']['target_bundles']['#value'],
    ];
    $form_state->setValueForElement($element, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id,
    $bundle,
    $label,
    $uid,
    $parents = NULL) {
    if ($this->entityTypeManager->getAccessControlHandler($entity_type_id)
      ->createAccess($bundle)) {
      $values = [
        'vid' => $bundle,
        'name' => $label,
        'parent' => $parents,
      ];
      return $this->entityTypeManager
        ->getStorage($entity_type_id)->create($values);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities, $parents = NULL) {
    return array_filter($entities, function ($entity) {
      $target_bundles = $this->getConfiguration()['target_bundles'];
      if (isset($target_bundles)) {
        return in_array($entity->bundle(), $target_bundles);
      }
      return TRUE;
    });
  }

}
