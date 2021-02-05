<?php

namespace Drupal\docstore;

use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Build metadata structure.
 */
trait MetadataTrait {

  /**
   * Build item data from metadata.
   *
   * @return array
   *   Item for Entity::create.
   */
  protected function buildItemDataFromMetaData($metadata, $type, $provider, $author, $entity_type) {
    $item = [];

    if (!is_array($metadata) || $this->arrayIsAssociative($metadata)) {
      throw new \Exception('Metadata has to be an array');
    }

    foreach ($metadata as $metaitem) {
      foreach ($metaitem as $key => $values) {
        // Check for label keys.
        if (strpos($key, '_label')) {
          $key = str_replace('_label', '', $key);
          $values = $this->mapOrCreateTerms($key, $values, $type, $provider, $author, $entity_type);
        }

        // Make sure user has access to the field.
        if (!$this->providerCanUseField($key, $type, $entity_type, $provider)) {
          throw new \Exception(strtr('You do not have access to field @field', [
            '@field' => $key,
          ]));
        }

        // Plain values.
        if (!is_array($values)) {
          $item[$key][] = [
            'value' => $values,
          ];
        }
        else {
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
              if (isset($value['_action']) && $value['_action'] === 'lookup') {
                // Lookup target.
                $entity = $this->findTargetByProperty($value['_reference'], $value['_target'], $value['_field'], $value['value']);
                if ($entity) {
                  $item[$key][] = [
                    'target_uuid' => $entity->uuid(),
                  ];
                }
              }
              elseif (isset($value['_action']) && $value['_action'] === 'create') {
                // Allow on the fly creation of child items.
                if ($value['_reference'] === 'node') {
                  $item[$key][] = [
                    'target_uuid' => $this->createChildDocument($author, $value, $provider),
                  ];
                }

                if ($value['_reference'] === 'term') {
                  $item[$key][] = [
                    'target_uuid' => $this->createChildTerm($author, $value, $provider),
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
    }

    return $item;
  }

  /**
   * Create a child document from the provided data.
   *
   * @param string $author
   *   Author (same as parent).
   * @param array $data
   *   Data to create the document with.
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   *
   * @return string
   *   Document uuid.
   */
  protected function createChildDocument($author, array $data, AccountInterface $provider) {
    $document_controller = \Drupal::service('docstore.document_controller');

    // Add author.
    if (!isset($data['_data']['author'])) {
      $data['_data']['author'] = $author;
    }

    $node_type = $document_controller
      ->loadNodeType($data['_target']);

    $result = $document_controller
      ->createDocumentFromParameters($node_type, $data['_data'], $provider);

    return $result['uuid'];
  }

  /**
   * Create a child term from the provided data.
   *
   * @param string $author
   *   Author (same as parent).
   * @param array $data
   *   Data to create the term with.
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   *
   * @return string
   *   Term uuid.
   */
  protected function createChildTerm($author, array $data, AccountInterface $provider) {
    $vocabulary_controller = \Drupal::service('docstore.vocabulary_controller');

    // Add author.
    if (!isset($data['_data']['author'])) {
      $data['_data']['author'] = $author;
    }

    $vocabulary = $vocabulary_controller
      ->loadVocabulary($data['_target']);

    $result = $vocabulary_controller
      ->createTermFromParameters($vocabulary, $data['_data'], $provider);

    return $result['uuid'];
  }

  /**
   * Map or create terms based on field and label.
   */
  protected function mapOrCreateTerms($field_name, $values, $type, $provider, $author, $entity_type) {
    if ($entity_type === 'node') {
      $field = FieldConfig::loadByName('node', $type, $field_name);
    }
    else {
      $field = FieldConfig::loadByName('taxonomy_term', $type, $field_name);
    }

    if (!$field) {
      throw new \Exception(strtr('Field @field does not exist on @type', [
        '@field' => $field_name,
        '@type' => $type,
      ]));
    }

    if ($field->getType() !== 'entity_reference_uuid') {
      throw new \Exception(strtr('Field @field is not a reference field', ['@field' => $field]));
    }

    if ($field->getSetting('target_type') !== 'taxonomy_term') {
      throw new \Exception(strtr('Field @field does not reference a vocabulary', ['@field' => $field]));
    }

    $handler_settings = $field->getSetting('handler_settings');
    $bundles = array_values($handler_settings['target_bundles']);
    $bundle = reset($bundles);

    // Get the vocabulary controller to create the terms.
    $vocabulary_controller = \Drupal::service('docstore.vocabulary_controller');

    // Load vocabulary.
    $vocabulary = $vocabulary_controller->loadVocabulary($bundle);

    // Loop values.
    if (!is_array($values)) {
      $values = [$values];
    }

    foreach ($values as $key => &$value) {
      // Skip empty values.
      if (empty(trim($value))) {
        unset($values[$key]);
        continue;
      }

      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'name' => $value,
        'vid' => $vocabulary->id(),
      ]);

      if ($terms) {
        $term = reset($terms);
        $value = $term->uuid();

        continue;
      }

      // Check if provider can create terms.
      if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $provider->uuid()) {
        if (!$vocabulary->getThirdPartySetting('docstore', 'content_allowed', FALSE)) {
          throw new \Exception(strtr('You are not allowed to create new terms in @vocabulary', ['@vocabulary' => $vocabulary->label()]));
        }
      }

      // Create term.
      $params = [
        'label' => $value,
        'author' => $author,
      ];

      $result = $vocabulary_controller
        ->createTermFromParameters($vocabulary, $params, $provider);

      $value = $result['uuid'];
    }

    return $values;
  }

  /**
   * Look up target by property.
   */
  protected function findTargetByProperty($reference, $target, $field, $value) {
    $entities = [];

    if ($reference === 'node') {
      $entities = $this->entityTypeManager->getStorage($reference)->loadByProperties([
        $field => $value,
        'type' => $target,
      ]);
    }
    else {
      $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        $field => $value,
        'vid' => $target,
      ]);
    }

    if ($entities) {
      return reset($entities);
    }
  }

  /**
   * Update the fields of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Fieldable entity (ex: node, term).
   * @param array $params
   *   Parameters.
   * @param array $protected_fields
   *   List of fields that cannot be changed.
   *
   * @return array
   *   List of updated fields with field names as keys.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the field cannot be changed or doesn't exist.
   */
  public function updateEntityFieldsFromParameters(FieldableEntityInterface $entity, array $params, array $protected_fields = []) {
    $updated_fields = [];

    // Update all fields specified in metadata.
    if (isset($params['metadata'])) {
      $metadata = $params['metadata'];
      if (!is_array($metadata) || $this->arrayIsAssociative($metadata)) {
        throw new BadRequestHttpException('Metadata has to be an array');
      }

      foreach ($metadata as $metaitem) {
        foreach ($metaitem as $name => $values) {
          $updated_fields[$this->setEntityFieldValue($entity, $name, $values, $protected_fields)] = TRUE;
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

      $updated_fields[$this->setEntityFieldValue($entity, $name, $values, $protected_fields)] = TRUE;
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
   * @param array $protected_fields
   *   List of fields that cannot be changed.
   *
   * @return string
   *   Field name.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the field cannot be changed or doesn't exist.
   */
  public function setEntityFieldValue(FieldableEntityInterface $entity, $name, $values, array $protected_fields = []) {
    // Make sure protected fields aren't set.
    if (isset($protected_fields[$name])) {
      throw new BadRequestHttpException(strtr('Field @name cannot be changed', ['@name' => $name]));
    }

    if ($entity->hasField($name)) {
      // @todo explain why we do that.
      // @see https://github.com/UN-OCHA/docstore-site/pull/75
      if (is_array($values) && !$this->arrayIsAssociative($values)) {
        foreach ($values as $key => $value) {
          $values[$key] = isset($value['uuid']) ? $value['uuid'] : $value;
        }
      }
      $entity->set($name, $values);
    }
    else {
      throw new BadRequestHttpException(strtr('Field @name does not exist', ['@name' => $name]));
    }

    return $name;
  }

  /**
   * Empty the given entity's fields.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Fieldable entity (ex: node, term).
   * @param array $skip_fields
   *   List of fields to skip.
   */
  public function emptyEntityFields(FieldableEntityInterface $entity, array $skip_fields = []) {
    $fields = $entity->getFields(FALSE);
    foreach ($fields as $field) {
      $name = $field->getName();

      if (!isset($skip_fields[$name]) && !$field->isEmpty()) {
        $entity->set($name, NULL);
      }
    }
  }

  /**
   * Create a new revision for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity (ex: term, node).
   * @param array $params
   *   Request parameters.
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   *
   * @todo maybe this should be moved to the ResourceTrait?
   */
  public function createEntityRevisionFromParameters(ContentEntityInterface $entity, array $params, AccountInterface $provider) {
    // Check if we need to create a new revision.
    $entity->setRevisionCreationTime(time());

    // Check if instructed to create a new revision.
    if (!empty($params['new_revision'])) {
      $create_revision = TRUE;
    }
    // Otherwise check if revisions should always be created for this resource.
    else {
      // Get the bundle entity (vocabulary, node type etc.).
      $bundle_entity = $entity->get($entity->getEntityType()->getKey('bundle'))->entity;

      if ($bundle_entity instanceof RevisionableEntityBundleInterface) {
        $create_revision = $bundle_entity->shouldCreateNewRevision();
      }
      else {
        $create_revision = $bundle_entity->getThirdPartySetting('docstore', 'use_revisions', TRUE);
      }
    }

    // Create a new revision.
    if ($create_revision) {
      $entity->revision_log = 'Updated';
      // @todo add some validation.
      if (isset($params['revision_log'])) {
        $entity->revision_log = $params['revision_log'];
      }

      $entity->setNewRevision();
      $entity->setRevisionUserId($provider->id());

      // Save new revision as draft?
      $entity->isDefaultRevision(TRUE);
      if (!empty($params['draft'])) {
        $entity->isDefaultRevision(FALSE);
      }
    }
  }

  /**
   * Publish entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity (ex: term, node).
   * @param array $params
   *   Request parameters.
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   *
   * @todo maybe this should be moved to the ResourceTrait?
   */
  public function publishEntityRevisionFromParameters(ContentEntityInterface $entity, array $params, AccountInterface $provider) {
    if (!$entity->isDefaultRevision()) {
      $entity->setRevisionCreationTime(time());

      $entity->revision_log = 'Updated';
      // @todo add some validation.
      if (isset($params['revision_log'])) {
        $entity->revision_log = $params['revision_log'];
      }

      $entity->setNewRevision();
      $entity->setRevisionUserId($provider->id());

      $entity->isDefaultRevision(TRUE);
      $entity->save();
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
      throw new BadRequestHttpException(strtr('Unable to save document: @error (@path)', [
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
    return !empty($this->entityUsage->listSources($entity));
  }

  /**
   * Check if a provider has access to the field.
   *
   * @param string $field_name
   *   Field name.
   * @param string $bundle
   *   Entity bundle (vocabulary or node type).
   * @param string $entity_type
   *   Entity type (term, taxonomy_term or node).
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   *
   * @return bool
   *   Whether the provider can use the field or not.
   *
   * @throws \Exception
   *   Generic execption when the field doesn't exist.
   *
   * @todo review the exception and make that more generic, for example if we
   * want to allow metadata on providers or files.
   *
   * @todo review if this shouldn't be moved to the ProviderTrait instead to
   * go with the other `providerCan` methods.
   */
  protected function providerCanUseField($field_name, $bundle, $entity_type, AccountInterface $provider) {
    switch ($entity_type) {
      case 'term':
      case 'taxonomy_term':
        // Use real entity type.
        $entity_type = 'taxonomy_term';

        $public_fields = [
          'default_langcode',
          'description',
          'langcode',
          'name',
          'revision_default',
          'revision_translation_affected',
          'status',
          'uuid',
        ];
        break;

      case 'node':
        $public_fields = [
          'boost_document',
          'changed',
          'created',
          'langcode',
          'provider',
          'published',
          'rendered_item',
          'search_api_id',
          'search_api_relevance',
          'title',
          'type',
          'uuid',
          'vid',
        ];
        break;
    }

    static $cache = [];

    if (isset($cache[$entity_type][$bundle][$field_name][$provider->id()])) {
      return $cache[$entity_type][$bundle][$field_name][$provider->id()];
    }

    // Public fields are usable by any provider.
    if (in_array($field_name, $public_fields)) {
      $cache[$entity_type][$bundle][$field_name][$provider->id()] = TRUE;
      return TRUE;
    }

    // Check if the field exists and get its configuration.
    $field_config = FieldConfig::loadByName($entity_type, $bundle, $field_name);
    if (!$field_config) {
      // @todo review what type of exception to send.
      throw new \Exception(strtr('Field @field does not exist', [
        '@field' => $field_name,
      ]));
    }

    // Check if the given provider is the provider of the field.
    if (!$provider->isAnonymous() && $field_config->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      $cache[$entity_type][$bundle][$field_name][$provider->id()] = TRUE;
      return TRUE;
    }

    // Otherwise check if the field is not private, in which case any provider
    // can access the field.
    if (!$field_config->getThirdPartySetting('docstore', 'private', FALSE)) {
      $cache[$entity_type][$bundle][$field_name][$provider->id()] = TRUE;
      return TRUE;
    }

    // Otherwise deny access to the field.
    $cache[$entity_type][$bundle][$field_name][$provider->id()] = FALSE;
    return FALSE;
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
