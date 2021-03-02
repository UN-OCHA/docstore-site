<?php

namespace Drupal\docstore;

use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Build metadata structure.
 */
trait MetadataTrait {

  /**
   * Build item data from metadata.
   *
   * @param mixed $params
   *   Metadata (list of associative arrays keyed for example with the field
   *   name and with the field value as value).
   * @param string $entity_type_id
   *   Entity type id (ex: node, taxonomy_term).
   * @param string $bundle
   *   Entity bundle.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string $author
   *   Author of the entity creation/update.
   *
   * @return array
   *   Item for Entity::create.
   *
   * @todo describe the different format of the metadata elements.
   */
  protected function buildItemDataFromParams($params, $entity_type_id, $bundle, UserInterface $provider, $author) {
    $item = [];

    // Fields already processed.
    $already_processed = [
      'title',
      'label',
      'author',
      'published',
      'private',
      'files',
    ];

    // Get the list of available fields for the entity type.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    foreach ($params as $key => $values) {
      if (in_array($key, $already_processed)) {
        continue;
      }

      $term_lookup = FALSE;
      // Handle special syntax to lookup/create terms on the fly.
      //
      // @todo we need to prevent the creation of fields with `_label` as
      // suffix.
      // @todo replace the syntax. For example `{"myfield": {"label": xxxx}}`.
      // @todo that catches fields like `aaaa_label_bbb`.
      if (strpos($key, '_label') !== FALSE) {
        $key = str_replace('_label', '', $key);
        $term_lookup = TRUE;
      }

      // Make sure the field exists.
      if (!isset($field_definitions[$key])) {
        throw new BadRequestHttpException(strtr('Field @field does not exist', [
          '@field' => $key,
        ]));
      }

      // Make sure user has access to the field.
      if (!$this->providerCanUseField($key, $entity_type_id, $bundle, $provider, FALSE)) {
        throw new AccessDeniedHttpException(strtr('You do not have access to field @field', [
          '@field' => $key,
        ]));
      }

      // Attempt to retrieve or generate the terms for the given term labels.
      // The result is a list of term uuids.
      if ($term_lookup) {
        $values = $this->mapOrCreateTerms($key, $values, $entity_type_id, $bundle, $provider, $author);
        foreach ($values as $value) {
          $item[$key][] = [
            'target_uuid' => $value,
          ];
        }
      }
      // Plain values.
      elseif (!is_array($values)) {
        $item[$key][] = [
          'value' => $values,
        ];
      }
      else {
        // @todo check if necessary.
        if (!isset($item[$key])) {
          $item[$key] = [];
        }

        // Ensure the values are an array of values/actions.
        if ($this->arrayIsAssociative($values)) {
          $values = [$values];
        }

        // Multiple values as array.
        foreach ($values as $value) {
          // Nested array.
          if (is_array($value)) {
            // Lookup target document or term.
            if (isset($value['_action']) && $value['_action'] === 'lookup') {
              $target_entity = $this->findTargetByProperty($value);
              if (!empty($target_entity)) {
                $item[$key][] = [
                  'target_uuid' => $target_entity->uuid(),
                ];
              }
            }
            // Allow on the fly creation of child documents or terms.
            elseif (isset($value['_action']) && $value['_action'] === 'create') {
              $child_resource = $this->createChildResource($author, $value, $provider);
              if (!empty($child_resource['uuid'])) {
                $item[$key][] = [
                  'target_uuid' => $child_resource['uuid'],
                ];
              }
            }
            else {
              // No action defined, pass as multi property.
              $item[$key][] = $value;
            }
          }
          else {
            // Assume it's a uuid.
            $item[$key][] = [
              'target_uuid' => $value,
            ];
          }
        }
      }
    }

    return $item;
  }

  /**
   * Create a child resource from the provided data.
   *
   * @param string $author
   *   Author (same as parent).
   * @param array $data
   *   Data to create the document with. This should be an associative array
   *   with the following properties:
   *   - "_reference": entity type id (ex: node, taxonomy_term), sometimes
   *     aliased to the type of resources like document or term.
   *   - "_target": entity bundle.
   *   - "_data": actual data to create the document with.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   Resource's data with at least its `uuid` property. An empty array is
   *   returned if no resource could be created, for example if the reference
   *   or target was not found.
   */
  protected function createChildResource($author, array $data, UserInterface $provider) {
    if (!isset($data['_reference'], $data['_target'], $data['_data'])) {
      return [];
    }

    // Add author.
    if (!isset($data['_data']['author'])) {
      $data['_data']['author'] = $author;
    }

    // Get the controller for the resource.
    switch ($data['_reference']) {
      case 'document':
      case 'node':
        $controller = \Drupal::service('docstore.document_controller');
        $node_type = $controller->loadNodeType($data['_target']);
        return $controller->createDocumentFromParameters($node_type, $data['_data'], $provider);

      case 'term':
      case 'taxonomy_term':
        $controller = \Drupal::service('docstore.term_controller');
        $vocabulary = $controller->loadVocabulary($data['_target']);
        return $controller->createTermFromParameters($vocabulary, $data['_data'], $provider);
    }

    return [];
  }

  /**
   * Map or create terms based on field and label.
   *
   * @param string $field_name
   *   Field name.
   * @param mixed $values
   *   Field values.
   * @param string $entity_type_id
   *   Entity type id (ex: node, taxonomy_term).
   * @param string $bundle
   *   Entity bundle.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string $author
   *   Author of the entity creation/update.
   *
   * @return array
   *   List of term uuids.
   */
  protected function mapOrCreateTerms($field_name, $values, $entity_type_id, $bundle, UserInterface $provider, $author) {
    $field = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
    if (empty($field)) {
      throw new BadRequestHttpException(strtr('Field @field does not exist on @bundle', [
        '@field' => $field_name,
        '@bundle' => $bundle,
      ]));
    }

    if ($field->getType() !== 'entity_reference_uuid') {
      throw new BadRequestHttpException(strtr('Field @field is not a reference field', [
        '@field' => $field,
      ]));
    }

    if ($field->getSetting('target_type') !== 'taxonomy_term') {
      throw new BadRequestHttpException(strtr('Field @field does not reference a vocabulary', [
        '@field' => $field,
      ]));
    }

    $handler_settings = $field->getSetting('handler_settings');
    $bundles = array_values($handler_settings['target_bundles']);
    $bundle = reset($bundles);

    // Get the term controller to create the terms.
    $term_controller = \Drupal::service('docstore.term_controller');

    // Load vocabulary.
    $vocabulary = $term_controller->loadVocabulary($bundle);

    // Loop values.
    if (!is_array($values)) {
      $values = [$values];
    }
    foreach ($values as $key => $value) {
      // @todo we're doing that only here. That should be part of a larger
      // validation process.
      $value = trim($value);

      // Skip empty values.
      if (empty($value)) {
        unset($values[$key]);
        continue;
      }

      // Retrieve the terms with the given label.
      // @todo use a query to get the term id to avoid loading terms just to
      // get their uuid. Also lots of terms with the same name could cause
      // performance issues.
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'name' => $value,
        'vid' => $vocabulary->id(),
      ]);

      // If at least one term with this label was found, use it's uuid as value
      // for the field.
      if (!empty($terms)) {
        $term = reset($terms);
        $values[$key] = $term->uuid();
        continue;
      }

      // Check if provider can create terms.
      // @todo use ProviderTrait::providerCanCreateUpdateDelete().
      if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $provider->uuid()) {
        if (!$vocabulary->getThirdPartySetting('docstore', 'content_allowed', FALSE)) {
          throw new AccessDeniedHttpException(strtr('You are not allowed to create new terms in @vocabulary', [
            '@vocabulary' => $vocabulary->label(),
          ]));
        }
      }

      // Create term.
      $params = [
        'label' => $value,
        'author' => $author,
      ];

      $result = $term_controller
        ->createTermFromParameters($vocabulary, $params, $provider);

      $values[$key] = $result['uuid'];
    }

    return $values;
  }

  /**
   * Look up target by property.
   *
   * @param array $data
   *   Data to find the target entity with. This should be an associative
   *   array with the following properties:
   *   - "_reference": entity type id (ex: node, taxonomy_term), sometimes
   *     aliased to the type of resources like document or term.
   *   - "_target": entity bundle.
   *   - "_field": field name used for the lookup.
   *   - "_value": field value used for the lookup.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Entity matching the criteria or NULL if none were found.
   *
   * @todo throw an error if no target was found?
   */
  protected function findTargetByProperty(array $data) {
    if (!isset($data['_reference'], $data['_target'], $data['_field'], $data['value'])) {
      return NULL;
    }

    $reference = $data['_reference'];
    $target = $data['_target'];

    // Normalize the reference.
    switch ($reference) {
      case 'document':
        $reference = 'node';
        break;

      case 'term':
        $reference = 'taxonomy_term';
        break;
    }

    // Attempt to load the storage for the entity type.
    $storage = $this->entityTypeManager->getStorage($reference);
    if (empty($storage)) {
      throw new BadRequestHttpException(strtr('Unknown reference @reference', [
        '@reference' => $reference,
      ]));
    }

    // Get the bundle field for the entity type.
    $bundle_field = $storage->getEntityType()->getKey('bundle');
    if (empty($bundle_field)) {
      throw new BadRequestHttpException(strtr('Unknown target @target', [
        '@target' => $target,
      ]));
    }

    // Load the entities with the given bundle and field value.
    $entities = $storage->loadByProperties([
      $bundle_field => $target,
      $data['_field'] => $data['_value'],
    ]);

    // Return the first found entity.
    return !empty($entities) ? reset($entities) : NULL;
  }

  /**
   * Update the fields of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Fieldable entity (ex: node, term).
   * @param array $params
   *   Parameters.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   List of updated fields with field names as keys.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the field cannot be changed or doesn't exist.
   */
  public function updateEntityFieldsFromParameters(FieldableEntityInterface $entity, array $params, UserInterface $provider) {
    $updated_fields = [];

    // Update all fields specified in metadata.
    if (isset($params['metadata'])) {
      $metadata = $params['metadata'];
      if (!is_array($metadata) || $this->arrayIsAssociative($metadata)) {
        throw new BadRequestHttpException('Metadata has to be an array');
      }

      foreach ($metadata as $metaitem) {
        foreach ($metaitem as $name => $values) {
          $updated_fields[$this->setEntityFieldValue($entity, $name, $values, $provider)] = TRUE;
        }
      }
      unset($params['metadata']);
    }

    // Update all fields specified in params.
    foreach ($params as $name => $values) {
      // Ignore revision fields.
      if ($name === 'new_revision' || $name === 'revision_log' || $name === 'draft') {
        continue;
      }

      $updated_fields[$this->setEntityFieldValue($entity, $name, $values, $provider)] = TRUE;
    }

    return $updated_fields;
  }

  /**
   * Set the field values of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Fieldable entity (ex: node, term).
   * @param string $name
   *   Field name.
   * @param mixed $values
   *   Field values.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return string
   *   Field name.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the field cannot be changed or doesn't exist.
   *
   * @todo pass the provider and use $this->getWritableFields().
   */
  public function setEntityFieldValue(FieldableEntityInterface $entity, $name, $values, UserInterface $provider) {
    // Check if the field is writable.
    $writable_fields = $this->getWritableFields($entity->getEntityTypeId(), $entity->bundle(), $provider);
    if (!isset($writable_fields[$name])) {
      throw new BadRequestHttpException(strtr('Field @name cannot be changed', [
        '@name' => $name,
      ]));
    }

    if ($entity->hasField($name)) {
      // @todo get the field definition and do appropriate handling of the
      // value based on that with proper validation too.
      if (is_array($values) && !$this->arrayIsAssociative($values)) {
        foreach ($values as $key => $value) {
          // Flatten the value for entity references.
          if (isset($value['uuid'])) {
            $values[$key] = $value['uuid'];
          }
          elseif (isset($value['file_uuid'])) {
            $values[$key] = $value['file_uuid'];
          }
          elseif (isset($value['media_uuid'])) {
            $values[$key] = $value['media_uuid'];
          }
        }
      }
      $entity->set($name, $values);
    }
    else {
      throw new BadRequestHttpException(strtr('Field @name does not exist', [
        '@name' => $name,
      ]));
    }

    return $name;
  }

  /**
   * Reset the given entity's fields.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Fieldable entity (ex: node, term).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param array $skip_fields
   *   List of fields to skip.
   */
  public function resetEntityFields(FieldableEntityInterface $entity, UserInterface $provider, array $skip_fields = []) {
    $entity_type_id = $entity->getEntityTypeId();

    // Check if the field is writable.
    $writable_fields = $this->getWritableFields($entity_type_id, $entity->bundle(), $provider);

    // Preserve the hierarchy if any as trying to reset it on a draft entity
    // may cause issues.
    unset($writable_fields['parent']);

    // Get the base fields. Those cannot be resetted.
    $base_field_definitions = $this->entityFieldManager
      ->getBaseFieldDefinitions($entity_type_id);

    // Get the list of non-computed fields.
    $fields = $entity->getFields(FALSE);
    foreach ($fields as $field) {
      $name = $field->getName();

      // Skip fields (usually fields that were just updated)
      if (isset($skip_fields[$name])) {
        continue;
      }

      // Skip the field if it's not writable by the provider. This would let
      // private fields from other providers intact.
      if (!isset($writable_fields[$name])) {
        continue;
      }

      // For writable base fields (ex: langcode), we reset to the default value.
      if (isset($base_field_definitions[$name])) {
        $field->applyDefaultValue();
      }
      // Otherwise we simply empty the field if not already.
      elseif (!$field->isEmpty()) {
        $entity->set($name, NULL);
      }
    }
  }

  /**
   * Validate and save an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity (ex: term, node).
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if there are validation issues.
   *
   * @todo maybe this should be moved to the ResourceTrait?
   */
  public function validateAndSaveEntity(ContentEntityInterface $entity) {
    // Trigger validation.
    $violations = $entity->validate();
    if (count($violations) > 0) {
      // We only show the first violation.
      throw new BadRequestHttpException(strtr('Unable to save resource: @error (@path)', [
        '@error' => strip_tags($violations->get(0)->getMessage()),
        '@path' => $violations->get(0)->getPropertyPath(),
      ]));
    }

    $entity->save();
  }

  /**
   * Validate that an entity's bundle matches the given bundle entity.
   *
   * \Drupal\Core\Entity\EntityInterface $entity
   *   Entity (ex: node, term).
   * \Drupal\Core\Entity\EntityInterface $bundle_entity
   *   Bundle entity (ex: node type, vocabulary)
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the bundles don't match.
   */
  protected function validateEntityBundle(EntityInterface $entity, EntityInterface $bundle_entity) {
    if ($entity->bundle() !== $bundle_entity->id()) {
      throw new BadRequestHttpException(strtr('Wrong @type', [
        '@type' => $bundle_entity->getEntityTypeId(),
      ]));
    }
  }

  /**
   * Check if in entity is in use.
   *
   * \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if entity is used somewhere.
   *
   * @todo maybe this should be moved to the ResourceTrait?
   */
  protected function entityInUse(EntityInterface $entity) {
    return isset($this->entityUsage) && !empty($this->entityUsage->listSources($entity));
  }

  /**
   * Check if a provider has access to the field.
   *
   * @param string $field_name
   *   Field name.
   * @param string $entity_type_id
   *   Entity type (term, taxonomy_term or node).
   * @param string $bundle
   *   Entity bundle (vocabulary or node type).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $read
   *   Whether to check if the provider can read the field or write to it.
   *
   * @return bool
   *   Whether the provider can use the field or not.
   *
   * @todo review if this shouldn't be moved to the ProviderTrait instead to
   * go with the other `providerCan` methods.
   */
  protected function providerCanUseField($field_name, $entity_type_id, $bundle, UserInterface $provider, $read = TRUE) {
    if ($read) {
      $fields = $this->getReadableFields($entity_type_id, $bundle, $provider);
    }
    else {
      $fields = $this->getWritableFields($entity_type_id, $bundle, $provider);
    }

    return isset($fields[$field_name]);
  }

  /**
   * Get the list of readable fields of the entity type, bundle and provider.
   *
   * @param string $entity_type_id
   *   Entity type (term, taxonomy_term or node).
   * @param string $bundle
   *   Entity bundle (vocabulary or node type).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   Lisf of readable fields keyed by actual fields and with normalized
   *   field names as values. The normalized field name should be the one to
   *   use when output an entity data in a response to improve the consistency
   *   between the different endpoints.
   */
  public function getReadableFields($entity_type_id, $bundle, UserInterface $provider) {
    static $cache = [];

    // Load from the cache if available as this function may be called several
    // times during the processing of an entity.
    if (isset($cache[$entity_type_id][$bundle][$provider->id()])) {
      return $cache[$entity_type_id][$bundle][$provider->id()];
    }

    // Lists of fields that can be accessed or not.
    $whitelist = [
      // Those are base fields so we whitelist them.
      'changed' => 'changed',
      'created' => 'created',
    ];
    $blacklist = [];

    // Base entity keys that a provider is allowed to read.
    $keys = [
      'uuid' => 'uuid',
      'bundle' => 'bundle',
      'revision' => 'revision_id',
      'label' => 'label',
      'published' => 'published',
      'langcode' => 'langcode',
      'owner' => 'owner',
    ];

    // Update the lists based on the entity type.
    switch ($entity_type_id) {
      case 'taxonomy_term':
        // Those are base fields so we whitelist them.
        $whitelist += [
          'parent' => 'parent',
          'description' => 'description',
        ];
        break;
    }

    /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->getEntityType();

    // Add the revision fields to the whitelist.
    $whitelist += $entity_type->getRevisionMetadataKeys();

    // Get the readable fields.
    $fields = $this->getAccessibleFields($entity_type_id, $bundle, $provider, $whitelist, $blacklist, $keys);

    // Store the list in the static cache.
    // @todo use \Drupal::state() instead?
    $cache[$entity_type_id][$bundle][$provider->id()] = $fields;

    return $fields;
  }

  /**
   * Get the list of writable fields of the entity type, bundle and provider.
   *
   * @param string $entity_type_id
   *   Entity type (term, taxonomy_term or node).
   * @param string $bundle
   *   Entity bundle (vocabulary or node type).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   Lisf of writable fields keyed by actual fields and with normalized
   *   field names as values. The normalized field name should be the one to
   *   use when output an entity data in a response to improve the consistency
   *   between the different endpoints.
   */
  public function getWritableFields($entity_type_id, $bundle, UserInterface $provider) {
    static $cache = [];

    // Load from the cache if available as this function may be called several
    // times during the processing of an entity.
    if (isset($cache[$entity_type_id][$bundle][$provider->id()])) {
      return $cache[$entity_type_id][$bundle][$provider->id()];
    }

    // Lists of fields that can be accessed or not.
    $whitelist = [];
    $blacklist = [
      // Those are "base fields" added by ManageFields.
      // @todo remove if those fields are added as real base fields.
      'author' => '',
      'created' => '',
      'provider_uuid' => '',
    ];

    // Base entity keys that a provider is allowed to modify.
    $keys = [
      'published' => 'published',
      'label' => 'label',
      'langcode' => 'langcode',
    ];

    // Update the lists based on the entity type.
    switch ($entity_type_id) {
      case 'taxonomy_term':
        $whitelist += [
          // @todo review if that should be whitelisted. Not sure how that
          // can be modified via the API.
          'parent' => 'parent',
          'description' => 'description',
        ];
        break;
    }

    // Get the writable fields.
    $fields = $this->getAccessibleFields($entity_type_id, $bundle, $provider, $whitelist, $blacklist, $keys);

    // Store the list in the static cache.
    // @todo use \Drupal::state() instead?
    $cache[$entity_type_id][$bundle][$provider->id()] = $fields;

    return $fields;
  }

  /**
   * Get a list of readable or writable fields.
   *
   * @param string $entity_type_id
   *   Entity type (ex: taxonomy_term, node).
   * @param string $bundle
   *   Entity bundle (vocabulary or node type).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param array $whitelist
   *   List of fields that are always accessible.
   * @param array $blacklist
   *   List of fields that are never accessible.
   * @param array $keys
   *   List of entity keys that should be accessible.
   *
   * @return array
   *   Lisf of accessible fields keyed by actual fields and with normalized
   *   field names as values. The normalized field name should be the one to
   *   use when output an entity data in a response to improve the consistency
   *   between the different endpoints.
   */
  public function getAccessibleFields($entity_type_id, $bundle, UserInterface $provider, array $whitelist = [], array $blacklist = [], array $keys = []) {
    // Get the field definitions that include base and custom fields.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    // Get the entity keys.
    $entity_keys = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->getEntityType()
      ->getKeys();

    // Add the entity keys matching the given ones, to the whitelist if not
    // already in the whitelist or blacklist.
    foreach ($keys as $key => $name) {
      // Skip if the key is not one of the entity keys.
      if (!isset($entity_keys[$key])) {
        continue;
      }
      $field = $entity_keys[$key];
      // Only add to the whitelist if not in the whitelist or blacklist.
      if (!isset($whitelist[$field], $blacklist[$field])) {
        $whitelist[$field] = $name;
      }
    }

    // Build the list of accessible fields.
    $accessible = [];
    foreach ($field_definitions as $field => $definition) {
      // Skip blacklisted fields.
      if (isset($blacklist[$field])) {
        continue;
      }

      // Add whitelisted fields to field without more checking.
      if (isset($whitelist[$field])) {
        $accessible[$field] = $whitelist[$field];
        continue;
      }

      // Skip base fields that were not whitelisted.
      if ($definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }

      // Load the field configuration.
      $field_config = $definition->getConfig($bundle);

      // Skip non existing fields. That should not happen as it was in the
      // field definition.
      if (empty($field_config)) {
        continue;
      }

      // If the field is not private, any body can access it.
      if (!$field_config->getThirdPartySetting('docstore', 'private', FALSE)) {
        $accessible[$field] = $field;
      }
      // Otheriwse check if the given provider is the provider of the field.
      elseif (!$provider->isAnonymous() && $field_config->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
        $accessible[$field] = $field;
      }
    }

    return $accessible;
  }

  /**
   * Check if an array is associative.
   *
   * @param array $array
   *   Array to check.
   *
   * @return bool
   *   TRUE if the array is an associative array.
   */
  protected function arrayIsAssociative(array $array) {
    foreach ($array as $key => $value) {
      if (is_string($key)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
