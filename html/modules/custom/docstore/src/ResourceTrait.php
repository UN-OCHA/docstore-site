<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldItemList;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeFieldItemList;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resource related functions.
 */
trait ResourceTrait {

  use MetadataTrait;
  use UtilityTrait;

  /**
   * Get the state key for the given entity type.
   *
   * @param string $bundle_type_id
   *   Entity type (ex: node_type or vocabulary).
   *
   * @return string
   *   State key.
   */
  public function getResourceTypeStateKey($bundle_type_id) {
    return 'docstore.resource_types.' . $this->getResourceType($bundle_type_id);
  }

  /**
   * Get a list of accessible resources of the goven type for the provider.
   *
   * @param string $bundle_type_id
   *   Bundle type (ex: node_type or vocabulary).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string $bundle
   *   If defined, check that the given bundle is among the accessible
   *   resources.
   *
   * @return array
   *   List of accessible resources (ids) for the provider.
   */
  public function getAccessibleResourceTypes($bundle_type_id, UserInterface $provider, $bundle = NULL) {
    // Nothing to do.
    if (!isset($this->entityTypeManager)) {
      return [];
    }

    // List of resources accessible to the provider.
    $accessible_resources = [];

    // Retrieve all the stored accessible resources of the given type.
    $state_key = $this->getResourceTypeStateKey($bundle_type_id);
    $resources = \Drupal::state()->get($state_key, []);

    // Load the resources accessible to the provider if not yet stored.
    if (!isset($resources[$provider->id()])) {
      // Retrieve the bundle entities for the given entity type.
      $entities = $this->entityTypeManager->getStorage($bundle_type_id)->loadMultiple();

      // Check if the provider can access those bundles.
      foreach ($entities as $entity) {
        if ($this->providerCanAccessResourceType($entity, $provider)) {
          $accessible_resources[$entity->id()] = $entity->id();
        }
      }

      // Update the global state.
      $resources[$provider->id()] = $accessible_resources;
      \Drupal::state()->set($state_key, $resources);
    }
    else {
      $accessible_resources = $resources[$provider->id()];
    }

    // Limit to the given bundle if defined.
    if (isset($bundle)) {
      if (!isset($accessible_resources[$bundle])) {
        // @todo maybe cache the response?
        throw new AccessDeniedHttpException(strtr('You do not have access to this @type', [
          '@type' => $this->getResourceTypeLabel($bundle_type_id, FALSE, TRUE),
        ]));
      }
      $accessible_resources = [$bundle => $bundle];
    }
    elseif (empty($accessible_resources)) {
      // @todo maybe cache the response?
      throw new AccessDeniedHttpException(strtr('No accessible @type available', [
        '@type' => $this->getResourceTypeLabel($bundle_type_id, TRUE, TRUE),
      ]));
    }

    return $accessible_resources;
  }

  /**
   * Check if the provider can access resources of the given type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityBundleBase $resource_type
   *   Docstore resource type (media type, node type or vocabulary).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return bool
   *   TRUE if the provider is allowed to access the given resource type.
   */
  protected function providerCanAccessResourceType(ConfigEntityBundleBase $resource_type, UserInterface $provider) {
    // Special case for the file media type which is always accessible as a
    // resource type. Media and file entities may on the other hand be private.
    if ($resource_type->id() === 'file') {
      return TRUE;
    }

    // Shared resources are always accessible.
    if ($resource_type->getThirdPartySetting('docstore', 'shared')) {
      return TRUE;
    }

    // Check if the current provider is the provider of the resource type.
    if ($resource_type->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Rebuild the list of accessible resources of the given entity type.
   *
   * @param string $bundle_type_id
   *   Bundle type (ex: node_type or vocabulary).
   * @param \Drupal\user\UserInterface|null $provider
   *   Provider.
   */
  public function rebuildAccessibleResourceTypes($bundle_type_id, ?UserInterface $provider = NULL) {
    \Drupal::state()->delete($this->getResourceTypeStateKey($bundle_type_id));

    if (isset($provider)) {
      $this->getAccessibleResourceTypes($bundle_type_id, $provider);
    }
  }

  /**
   * Load a resource entity.
   *
   * @param string $entity_type_id
   *   Entity type.
   * @param string $id
   *   Entity id, machine_name or uuid.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Entity.
   */
  public function loadResourceEntity($entity_type_id, $id) {
    if (!isset($this->entityTypeManager)) {
      throw new HttpException(500, 'Unable to load resource');
    }

    $entity = NULL;

    // Load by uuid.
    if (Uuid::isValid($id)) {
      if (isset($this->entityRepository)) {
        $entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $id);
      }
      // Equivalent of the above for when entityRepository is not available.
      // @todo remove if inject the entity repository in all controllers using
      // this trait.
      else {
        $storage = $this->entityTypeManager->getStorage($entity_type_id);
        $uuid_key = $storage->getEntityType()->getKey('uuid');
        if (!empty($uuid_key)) {
          $entities = $storage->loadByProperties([$uuid_key => $id]);
          $entity = $entities ? reset($entities) : NULL;
        }
      }
    }
    // Otherwise assume it's an entity id or machine name.
    else {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    }

    if (empty($entity)) {
      throw new NotFoundHttpException(strtr('@label does not exist', [
        '@label' => $this->getResourceTypeLabel($entity_type_id, FALSE),
      ]));
    }

    return $entity;
  }

  /**
   * Prepare the entity resource data to return in the response.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Resource entity (ex: node, term).
   * @param \Drupal\user\UserInterface $provider
   *   Provider. If defined, the data will only contains fields accessible
   *   to the provider.
   * @param bool $full_output
   *   If FALSE, only return the entity's uuid.
   *
   * @return array
   *   Resource's data ready for output in the response.
   */
  public function prepareEntityResourceDataForResponse(FieldableEntityInterface $entity, UserInterface $provider, $full_output = TRUE) {
    // Return the uuid only if instructed so.
    if (!$full_output) {
      return ['uuid' => $entity->uuid()];
    }

    $data = $this->prepareEntityResourceData($entity, $provider, FALSE);

    // Remove any field or file uri inaccessible by the provider.
    return $this->massageResourceDataForResponse($data, $entity->getEntityTypeId(), $entity->bundle(), $provider);
  }

  /**
   * Prepare the data to retun in the response from a resource entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Resource entity (ex: node, term).
   *
   * @return string
   *   Serialized resource's data.
   */
  public function prepareEntityResourceDataForStorage(FieldableEntityInterface $entity) {
    $data = $this->prepareEntityResourceData($entity, NULL, TRUE);
    return base64_encode(serialize($data));
  }

  /**
   * Prepare the data to store in solr or return in the resposnse.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Resource entity (ex: node, term).
   * @param \Drupal\user\UserInterface|null $provider
   *   Provider. If defined, the data will only contains fields accessible
   *   to the provider.
   * @param bool $expand_references
   *   Expand referenced nodes.
   *
   * @return array
   *   Resource's data.
   */
  public function prepareEntityResourceData(FieldableEntityInterface $entity, ?UserInterface $provider = NULL, $expand_references = TRUE) {
    $data = [];

    // Entity information.
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    // Get the list of readable fields, we'll only process those.
    //
    // If the provider is defined, private fields inaccessible to the provider
    // will already have been removed, preventing unnecessary processing below.
    $readable_fields = $this->getReadableFields($entity_type_id, $bundle, $provider);

    // Process the entity fields.
    $fields = $entity->getFields(TRUE);
    foreach ($fields as $field) {
      $field_name = $field->getName();

      // Skip fields not accessible to the provider.
      if (!isset($readable_fields[$field_name])) {
        continue;
      }

      // Get the field's data and skip if empty.
      $field_item_list = $entity->get($field_name)->filterEmptyItems();
      if ($field_item_list->isEmpty()) {
        continue;
      }

      // Get the field's definition.
      $field_item_definition = $field_item_list->getFieldDefinition();
      $field_type = $field_item_definition->getType();

      // Get the normalized field name.
      $normalized_field_name = $readable_fields[$field_name]['normalized_name'];

      // Process field values.
      // @todo handle geofield.
      switch ($field_type) {
        case 'boolean':
          $values = $this->prepareEntityBooleanField($field_item_list);
          break;

        case 'integer':
          $values = $this->prepareEntityIntegerField($field_item_list);
          break;

        case 'float':
          $values = $this->prepareEntityFloatField($field_item_list);
          break;

        case 'timestamp':
        case 'datetime':
        case 'created':
        case 'changed':
          $values = $this->prepareEntityDateField($field_item_list);
          break;

        case 'daterange':
          $values = $this->prepareEntityDateRangeField($field_item_list);
          break;

        case 'geofield':
          $values = $this->prepareEntityGeofieldField($field_item_list);
          break;

        case 'link':
          $values = $this->prepareEntitylinkField($field_item_list);
          break;

        case 'entity_reference':
        case 'entity_reference_uuid':
        case 'node_reference':
        case 'term_reference':
          if ($field_name === 'files') {
            $values = $this->prepareEntityFilesField($field_item_list);
          }
          else {
            $values = $this->prepareEntityEntityReferenceField($field_item_list, $provider, $expand_references);
          }
          break;

        default:
          $values = [];
          foreach ($field_item_list as $field_item) {
            $main_property_name = $field_item->mainPropertyName();

            if (!empty($main_property_name)) {
              $values[] = $field_item->getValue()[$main_property_name];
            }
            else {
              $values[] = $field_item->getValue();
            }
          }
      }

      // Skip if there is no values after processing.
      if (empty($values)) {
        continue;
      }

      // Convert field value based on cardinality.
      if (!$field_item_definition->getFieldStorageDefinition()->isMultiple()) {
        $data[$normalized_field_name] = reset($values);
      }
      else {
        $data[$normalized_field_name] = array_values($values);
      }
    }

    // Update the data based on the entity type.
    $this->massageResourceDataForEntityType($data, $entity_type_id, $bundle);

    return $data;
  }

  /**
   * Prepare the data for a boolean field.
   *
   * @param \Drupal\Core\Field\FieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of values all converted to booleans.
   */
  public function prepareEntityBooleanField(FieldItemList $list) {
    $data = [];
    foreach ($list as $item) {
      $data[] = !empty($item->get('value')->getValue());
    }
    return $data;
  }

  /**
   * Prepare the data for an integer field.
   *
   * @param \Drupal\Core\Field\FieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of values all converted to integers.
   */
  public function prepareEntityIntegerField(FieldItemList $list) {
    $data = [];
    foreach ($list as $item) {
      $data[] = (int) $item->get('value')->getValue();
    }
    return $data;
  }

  /**
   * Prepare the data for a float field.
   *
   * @param \Drupal\Core\Field\FieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of values all converted to floats.
   */
  public function prepareEntityFloatField(FieldItemList $list) {
    $data = [];
    foreach ($list as $item) {
      $data[] = (float) $item->get('value')->getValue();
    }
    return $data;
  }

  /**
   * Prepare the data for a date like field.
   *
   * @param \Drupal\Core\Field\FieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of values all converted to ISO 8601 dates.
   */
  public function prepareEntityDateField(FieldItemList $list) {
    $data = [];
    foreach ($list as $item) {
      $value = $this->formatIso8601Date($item->get('value')->getValue());
      if (!empty($value)) {
        $data[] = $value;
      }
    }
    return $data;
  }

  /**
   * Prepare the data for a date range field.
   *
   * @param \Drupal\datetime_range\Plugin\Field\FieldType\DateRangeFieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of values all converted to ISO 8601 dates.
   */
  public function prepareEntityDateRangeField(DateRangeFieldItemList $list) {
    $data = [];
    foreach ($list as $item) {
      $value = [];

      $start = $this->formatIso8601Date($item->get('value')->getValue());
      if (!empty($start)) {
        $value['start'] = $start;
      }

      $end = $this->formatIso8601Date($item->get('end_value')->getValue());
      if (!empty($end)) {
        $value['end'] = $end;
      }

      if (!empty($value)) {
        $data[] = $value;
      }
    }
    return $data;
  }

  /**
   * Prepare the data for a geofield field.
   *
   * @param \Drupal\Core\Field\FieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of values with lat and lon.
   */
  public function prepareEntityGeofieldField(FieldItemList $list) {
    $data = [];
    foreach ($list as $item) {
      $data[] = [
        'lat' => (float) $item->get('lat')->getValue(),
        'lon' => (float) $item->get('lon')->getValue(),
      ];
    }
    return $data;
  }

  /**
   * Prepare the data for a link field.
   *
   * @param \Drupal\Core\Field\FieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of values with link uri and title.
   */
  public function prepareEntityLinkField(FieldItemList $list) {
    $data = [];
    foreach ($list as $item) {
      $data[] = [
        'uri' => $item->get('uri')->getValue() ?: '',
        'title' => $item->get('title')->getValue() ?: '',
      ];
    }
    return $data;
  }

  /**
   * Prepare the data for an entity reference field.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemList $list
   *   Field item list.
   * @param \Drupal\user\UserInterface|null $provider
   *   Provider. If defined, the data will only contains fields accessible
   *   to the provider.
   * @param bool $expand_references
   *   Expand referenced nodes.
   *
   * @return array
   *   List of referenced entities with their uuid and label.
   *
   * @todo retrieve the private state of the referenced entities and store
   * the provider_uuid so we can exclude them if necessary before sending the
   * response data.
   */
  public function prepareEntityEntityReferenceField(EntityReferenceFieldItemList $list, ?UserInterface $provider = NULL, $expand_references = TRUE) {
    $data = [];

    foreach ($list->referencedEntities() as $entity) {
      $item = [
        'uuid' => $entity->uuid(),
      ];

      // Get the label key for the entity type.
      //
      // This ensures consistency with the resource labels:
      // - Node: title
      // - Term: label (this is the problematic one, using label instead name)
      // - Media: name.
      //
      // @see massageResourceDataForEntityType().
      //
      // @todo It would much easier if we were to use "name" for terms which is
      // the field name for the term label rather than "label" or if we were to
      // use "label" for all the entity types.
      if ($entity->getEntityTypeId() === 'taxonomy_term') {
        $label_key = 'label';
      }
      else {
        $label_key = $this->entityTypeManager
          ->getStorage($entity->getEntityTypeId())
          ->getEntityType()
          ->getKey('label');
      }

      // Add the referenced entity label if defined.
      if (!empty($label_key)) {
        $item[$label_key] = $entity->label();
      }

      // Add fields of referenced item.
      if ($entity->getEntityTypeId() === 'node' && $expand_references) {
        $extra_data = $this->prepareEntityResourceData($entity, $provider, FALSE);
        $item = array_merge($item, $extra_data);
      }

      // Add display label if there is one.
      if (isset($entity->display_label) && !$entity->display_label->isEmpty()) {
        $item['display_label'] = $entity->display_label->first()->getValue()['value'];
      }

      $data[] = $item;
    }

    return $data;
  }

  /**
   * Prepare the data for the files field.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of referenced entities with their uuid and label.
   */
  public function prepareEntityFilesField(EntityReferenceFieldItemList $list) {
    $data = [];

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($list->referencedEntities() as $media) {
      /** @var \Drupal\file\Entity\File $file */
      // @phpstan-ignore-next-line
      $file = $media->field_media_file->entity;
      if (empty($file)) {
        continue;
      }

      // Get the media entity data, passing the media owner as provider in
      // order to generate the private uri.
      $data[] = $this->prepareMediaEntityData($media, $file, $media->getOwner());
    }

    return $data;
  }

  /**
   * Build the media data for the json response.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\file\Entity\File $file
   *   File.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param array|null $revisions
   *   Revisions.
   *
   * @return array
   *   Dara for the JSON response.
   */
  public function prepareMediaEntityData(Media $media, File $file, UserInterface $provider, ?array $revisions = NULL) {
    $data = [
      'uuid' => $media->uuid(),
      // @todo For backward compatibility, remove.
      'media_uuid' => $media->uuid(),
      'revision_id' => $media->getRevisionId(),
      'filename' => $media->getName(),
      'created' => $this->formatIso8601Date($media->getCreatedTime()),
      'changed' => $this->formatIso8601Date($media->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'size' => $file->getSize(),
      'private' => $this->mediaIsPrivate($media),
    ];

    // Add the uri if the file is public or the provider is the owner.
    // @todo there is probably no harm in always showing the file uri as it
    // can easily be created from the data above: files/uuid/filename.
    if (!$file->isTemporary()) {
      $data['uri'] = static::getMediaUrl($media);
    }

    // If the file is private add the owner uuid information so that we can
    // filter out the file if the provider is not the owner.
    if (!empty($data['private'])) {
      $data['provider_uuid'] = $media->getOwner()->uuid();
    }

    // Add the media revisions if any.
    if (isset($revisions)) {
      $data['revisions'] = $revisions;
    }

    return $data;
  }

  /**
   * Prepare the resource's data for the response.
   *
   * This filters out private fields and hide the uri of private files that
   * are not owner by the provider.
   *
   * @param array $data
   *   Resource's data.
   * @param string $entity_type_id
   *   Entity type id of the resource.
   * @param string $bundle
   *   Entity bundle of the resource.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   Resource's data without the private fields and private file uris
   *   inaccessible to the provider.
   */
  public function massageResourceDataForResponse(array $data, $entity_type_id, $bundle, UserInterface $provider) {
    // Get the unfiltered list of readable fields.
    $readable_fields = $this->getReadableFields($entity_type_id, $bundle);

    // Remove inaccessible private fields.
    foreach ($data as $name => $value) {
      if (isset($readable_fields[$name]['provider_uuid']) && $readable_fields[$name]['provider_uuid'] !== $provider->uuid()) {
        unset($data[$name]);
      }
    }

    // Update private files.
    $provider_uuid = $provider->isAnonymous() ? NULL : $provider->uuid();
    if (!empty($data['files'])) {
      foreach ($data['files'] as &$file) {
        // Remove the uri if the provider is not the owner.
        if (!empty($file['private']) && (!isset($file['provider_uuid']) || $file['provider_uuid'] !== $provider_uuid)) {
          unset($file['uri']);
        }
        unset($file['provider_uuid']);
      }
    }

    // Remove any bundle added in massageResourceDataForEntityType().
    unset($data['bundle']);

    return $data;
  }

  /**
   * Update the resource data based on the entity type.
   *
   * @param array $data
   *   Resource data for response.
   * @param string $entity_type_id
   *   Entity type id of the resource.
   * @param string $bundle
   *   Entity bundle of the resource.
   */
  public function massageResourceDataForEntityType(array &$data, $entity_type_id, $bundle) {
    if (empty($data)) {
      return;
    }

    // @todo It would much easier if we were to use "name" for terms which is
    // the field name for the term label rather than "label" or if we were to
    // use "label" for all the entity types. Then we could avoid having to
    // check the entity type to decide which property to use for the entity
    // label and could simply handle the mapping via the
    // MetadataTrait::getReadableFields() and the like.
    switch ($entity_type_id) {
      case 'taxonomy_term':
        $data['vocabulary'] = $bundle;
        if (isset($data['bundle']['uuid'])) {
          $data['vocabulary_uuid'] = $data['bundle']['uuid'];
        }
        break;

      case 'node':
        $data['type'] = $bundle;
        if (isset($data['bundle']['uuid'])) {
          $data['type_uuid'] = $data['bundle']['uuid'];
        }
        if (isset($data['label'])) {
          $data['title'] = $data['label'];
          unset($data['label']);
        }
        break;

      case 'media':
        if (isset($data['label'])) {
          $data['name'] = $data['label'];
          unset($data['label']);
        }
        break;
    }

    // Store the bundle to ease extra processing. This will be removed before
    // returning the data for the response.
    // @see massageResourceDataForResponse()
    $data['bundle'] = $bundle;

    // Ensure the provider uuid is consistent.
    $this->massageUserUuidField($data, 'provider_uuid', 'owner');

    // Ensure the revision fields are consistent.
    $this->massageResourceRevisionFields($data);
  }

  /**
   * Ensure consistency of the revision fields in the response.
   *
   * @param array $data
   *   Resource data.
   */
  public function massageResourceRevisionFields(array &$data) {
    // Convert revision id to int.
    if (isset($data['revision_id'])) {
      $data['revision_id'] = (int) $data['revision_id'];
    }

    // Replace revision_default with draft.
    if (isset($data['revision_default'])) {
      if (empty($data['revision_default'])) {
        $data['draft'] = TRUE;
      }
      unset($data['revision_default']);
    }

    // Ensure the revision_provider_uuid field is populated.
    $this->massageUserUuidField($data, 'revision_provider_uuid', 'revision_user');

    // Rename the revision log message to revision_log to be consistent with
    // what we accept as parameter when creating a new revision via the API.
    // @see \Drupal\docstore\RevisionableEntity::createEntityRevisionFromParameters()
    if (isset($data['revision_log_message'])) {
      $data['revision_log'] = $data['revision_log_message'];
      unset($data['revision_log_message']);
    }
  }

  /**
   * Ensure the target field contains a user uuid.
   *
   * @param array $data
   *   Resource data from which to retrieve the user uuid for the target field.
   * @param string $target_field
   *   Target field to copy the user uuid to.
   * @param string $user_field
   *   Field that contains the user info from which to get the uuid.
   */
  public function massageUserUuidField(array &$data, $target_field, $user_field) {
    // If the target field is not defined but the user field is, then we
    // retrieve the user uuid form it.
    if (!isset($data[$target_field])) {
      if (isset($data[$user_field])) {
        // If the user field has a uuid, use it.
        if (isset($data[$user_field]['uuid'])) {
          $data[$target_field] = $data[$user_field]['uuid'];
        }
        // Otherwise we try load the user entity from its id which can be
        // the direct value of the user field or come from a uid property of it.
        else {
          if (is_scalar($data[$user_field])) {
            $uid = $data[$user_field];
          }
          elseif (isset($data[$user_field]['uid'])) {
            $uid = $data[$user_field]['uid'];
          }

          if (isset($uid)) {
            $user = $this->entityTypeManager
              ->getStorage('user')
              ->load($uid);
            if (!empty($user)) {
              $data[$target_field] = $user->uuid();
            }
          }
        }
      }
    }
    // If the target field exists and is an array with a uuid property then
    // we flatten it. Otherwise if it's a string then we assume the target
    // field value is already the uuid and we do nothing.
    elseif (!is_string($data[$target_field])) {
      if (isset($data[$target_field]['uuid'])) {
        $data[$target_field] = $data[$target_field]['uuid'];
      }
      // That should not happen but just to be on the safe side, delete it.
      else {
        unset($data[$target_field]);
      }
    }
    unset($data[$user_field]);
  }

}
