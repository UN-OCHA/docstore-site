<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
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
  use ProviderTrait;
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
      foreach ($entities as $entity) {
        // We catch the exception returned by providerCanRead() here because
        // we want to build a list of resources accessible to the provider so
        // not being allowed is ok in the context of this function. We'll simply
        // not store the resource as being accessible to the provider.
        try {
          if ($this->providerCanRead($entity, $provider)) {
            $accessible_resources[$entity->id()] = $entity->id();
          }
        }
        catch (AccessDeniedHttpException $exception) {
          // Skip.
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
   * Prepare the data to retun in the response from a resource entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Resource entity (ex: node, term).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $full_output
   *   Whether to return the full document's data or only the uuid.
   *
   * @return array
   *   Resource's data.
   *
   * @todo handle list of files for the resource item.
   *
   * @todo consolidate that between entity types. Hide unnecessary fields and
   * also make sure it's compatible with what is returned from Solr.
   */
  public function prepareEntityResourceData(FieldableEntityInterface $entity, UserInterface $provider, $full_output = TRUE) {
    $data = [];

    // Return the uuid only if instructed so.
    if (!$full_output) {
      return ['uuid' => $entity->uuid()];
    }

    // Entity information.
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    // Get the list of readable fields, we'll only return those.
    $readable_fields = $this->getReadableFields($entity_type_id, $bundle, $provider);

    // Process the entity fields.
    $fields = $entity->getFields(TRUE);

    foreach ($fields as $field) {
      $field_name = $field->getName();

      // Skip fields not accessible to the provider.
      if (!isset($readable_fields[$field_name])) {
        continue;
      }

      // Get the output field name.
      $output_field_name = $readable_fields[$field_name];

      // Get the field's data and skip if empty.
      $field_item_list = $entity->get($field_name)->filterEmptyItems();
      if ($field_item_list->isEmpty()) {
        continue;
      }

      // Get the field's definition.
      $field_item_definition = $field_item_list->getFieldDefinition();
      $field_type = $field_item_definition->getType();

      // Process field values.
      // @todo handle geofield.
      switch ($field_type) {
        case 'boolean':
          $values = $this->prepareEntityBooleanField($field_item_list);
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
            $values = $this->prepareEntityFilesField($field_item_list, $provider);
          }
          else {
            $values = $this->prepareEntityEntityReferenceField($field_item_list);
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
        $data[$output_field_name] = reset($values);
      }
      else {
        $data[$output_field_name] = array_values($values);
      }
    }

    // Update the data based on the entity type.
    $this->massageResourceDataForEntityType($data, $entity_type_id, $bundle);

    // @todo normalize revision fields and add revision_provider_uuid.
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
        'lat' => $item->get('lat')->getValue(),
        'lon' => $item->get('lon')->getValue(),
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
   * Prepare the data for the files field.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemList $list
   *   Field item list.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   List of referenced entities with their uuid and label.
   */
  public function prepareEntityFilesField(EntityReferenceFieldItemList $list, UserInterface $provider) {
    $data = [];

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($list->referencedEntities() as $media) {
      $file_id = $media->getSource()->getSourceFieldValue($media);
      if (empty($file_id)) {
        continue;
      }

      /** @var \Drupal\file\Entity\File $file */
      $file = $this->entityTypeManager->getStorage('file')->load($file_id);
      if (empty($file)) {
        continue;
      }

      $data[] = $this->prepareMediaEntityData($media, $file, $provider);
    }

    return $data;
  }

  /**
   * Prepare the data for an entity reference field.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemList $list
   *   Field item list.
   *
   * @return array
   *   List of referenced entities with their uuid and label.
   */
  public function prepareEntityEntityReferenceField(EntityReferenceFieldItemList $list) {
    $data = [];

    foreach ($list->referencedEntities() as $entity) {
      $item = [
        'uuid' => $entity->uuid(),
      ];

      // Get the label key for the entity type.
      $label_key = $this->entityTypeManager
        ->getStorage($entity->getEntityTypeId())
        ->getEntityType()
        ->getKey('label');

      // Add the referenced entity label if defined.
      if (!empty($label_key)) {
        $item[$label_key] = $entity->label();
      }

      $data[] = $item;
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
   *
   * @todo consolidate with prepareFileEntityData().
   * @todo add the media's provider uuid?
   */
  public function prepareMediaEntityData(Media $media, File $file, UserInterface $provider, ?array $revisions = NULL) {
    $data = [
      // @todo consolidate that with the prepareFileEntityData()'s media_uuid so
      // it's consistent between the endpoints.
      'uuid' => $media->uuid(),
      'filename' => $media->getName(),
      'created' => $this->formatIso8601Date($media->getCreatedTime()),
      'changed' => $this->formatIso8601Date($media->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'file_uuid' => $file->uuid(),
      'size' => $file->getSize(),
      'private' => $this->fileIsPrivate($file),
    ];

    if (!empty($data['private'])) {
      // For private files, we only add the uri if the provider is the owner
      // and we generate a direct URL rather than using the drupal internal
      // url.
      if ($this->providerIsOwner($media, $provider, 'owner_id', FALSE)) {
        $data['uri'] = $this->getMediaDirectUrl($media, $provider);
      }
    }
    else {
      // @todo wouldn't be better to generate a URL like the direct url for
      // private files rather than returning the drupal url. This would also
      // remove the URL swapping done when updating a file's content.
      // @see \Drupal\docstore\FileTrait::saveFileToDisk()
      $data['uri'] = static::getFileUrl($file);
    }

    // Add the media revisions if any.
    if (isset($revisions)) {
      $data['revisions'] = $revisions;
    }

    return $data;
  }

  /**
   * Build the file data for the json response.
   *
   * @param \Drupal\file\Entity\File $file
   *   File.
   * @param \Drupal\media\Entity\Media|null $media
   *   Media. It may be null if ther file is temporary (no content yet).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   Dara for the JSON response.
   *
   * @todo consolidate with prepareMediaEntityData().
   * @todo add a flag to indicate the file is temporary?
   * @todo add the file's provider uuid?
   */
  public function prepareFileEntityData(File $file, ?Media $media, UserInterface $provider) {
    $data = [
      // @todo consolidate that with the prepareMediaEntityData()'s file_uuid so
      // it's consistent between the endpoints.
      'uuid' => $file->uuid(),
      'filename' => $file->getFilename(),
      'created' => $this->formatIso8601Date($file->getCreatedTime()),
      'changed' => $this->formatIso8601Date($file->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'size' => $file->getSize(),
      'private' => $this->fileIsPrivate($file),
    ];

    if (!empty($media)) {
      $data['media_uuid'] = $media->uuid();
    }

    // Temporary files don't yet have a real uri as it's generated when
    // saving their content to disk.
    if (!$file->isTemporary()) {
      if (!empty($data['private'])) {
        // For private files, we only add the uri if the provider is the owner
        // and we generate a direct URL rather than using the drupal internal
        // url.
        if ($this->providerIsOwner($file, $provider, 'owner_id', FALSE)) {
          $data['uri'] = $this->getFileDirectUrl($file, $provider);
        }
      }
      else {
        // @todo wouldn't be better to generate a URL like the direct url for
        // private files rather than returning the drupal url. This would also
        // remove the URL swapping done when updating a file's content.
        // @see \Drupal\docstore\FileTrait::saveFileToDisk()
        $data['uri'] = static::getFileUrl($file);
      }
    }

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
    // Remove the bundle field as it's been transformed above.
    unset($data['bundle']);

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
