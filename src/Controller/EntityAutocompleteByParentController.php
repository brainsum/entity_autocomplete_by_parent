<?php

namespace Drupal\entity_autocomplete_by_parent\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Tags;
use Drupal\entity_autocomplete_by_parent\EntityAutocompleteMatcherByParent;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class EntityAutocompleteByParentController.
 */
class EntityAutocompleteByParentController extends ControllerBase {

  /**
   * The autocomplete matcher for entity references.
   *
   * @var \Drupal\entity_autocomplete_by_parent\EntityAutocompleteMatcherByParent
   */
  protected $matcher;

  /**
   * The key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValue;

  /**
   * Constructs a EntityAutocompleteController object.
   *
   * @param \Drupal\entity_autocomplete_by_parent\EntityAutocompleteMatcherByParent $matcher
   *   The autocomplete matcher for entity references.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value
   *   The key value factory.
   */
  public function __construct(EntityAutocompleteMatcherByParent $matcher, KeyValueStoreInterface $key_value) {
    $this->matcher = $matcher;
    $this->keyValue = $key_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_autocomplete_by_parent.autocomplete_matcher_by_parent'),
      $container->get('keyvalue')->get('entity_autocomplete')
    );
  }

  /**
   * Autocomplete the label of an entity.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that contains the typed tags.
   * @param string $target_type
   *   The ID of the target entity type.
   * @param string $selection_handler
   *   The plugin ID of the entity reference selection handler.
   * @param string $selection_settings_key
   *   The hashed key of the key/value entry that holds the selection handler
   *   settings.
   * @param string $parent
   *   The - separated parent arguments.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The matched entity labels as a JSON response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown if the selection settings key is not found in the key/value store
   *   or if it does not match the stored data.
   */
  public function handleAutocomplete(Request $request, $target_type, $selection_handler, $selection_settings_key, $parent) {
    $matches = [];
    // Get the typed string from the URL, if it exists.
    if ($input = $request->query->get('q')) {
      $typed_string = Tags::explode($input);
      $typed_string = mb_strtolower(array_pop($typed_string));

      // Selection settings are passed in as a hashed key of a serialized array
      // stored in the key/value store.
      $selection_settings = $this->keyValue->get($selection_settings_key, FALSE);
      if ($selection_settings !== FALSE) {
        $selection_settings_hash = Crypt::hmacBase64(serialize($selection_settings) . $target_type . $selection_handler, Settings::getHashSalt());
        if (!Crypt::hashEquals($selection_settings_hash, $selection_settings_key)) {
          // Disallow access when the selection settings hash does not match the
          // passed-in key.
          throw new AccessDeniedHttpException('Invalid selection settings key.');
        }
      }
      else {
        // Disallow access when the selection settings key is not found in the
        // key/value store.
        throw new AccessDeniedHttpException();
      }
      $arguments = explode('-', $parent);

      $matches = $this->matcher->getMatches($target_type, $selection_handler, $selection_settings, $typed_string, $arguments);
    }

    return new JsonResponse($matches);
  }

}
