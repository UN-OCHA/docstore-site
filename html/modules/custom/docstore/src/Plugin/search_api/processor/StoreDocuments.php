<?php

namespace Drupal\docstore\Plugin\search_api\processor;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\docstore\Plugin\search_api\processor\Property\StoreDocumentProperty;
use Drupal\docstore\UtilityTrait;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Adds customized aggregations of existing fields to the index.
 *
 * @see \Drupal\search_api\Plugin\docstore\processor\Property\StoreDocumentProperty
 *
 * @SearchApiProcessor(
 *   id = "docstore_store_document",
 *   label = @Translation("Store fields of a document"),
 *   description = @Translation("Store structured fields."),
 *   stages = {
 *     "add_properties" = 20,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class StoreDocuments extends ProcessorPluginBase {

  use UtilityTrait;

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Store fields of a document'),
        'description' => $this->t('Store fields of a document.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ];
      $properties['document_field'] = new StoreDocumentProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $item->getOriginalObject()->getValue();

    $fields_to_exclude = [
      'nid',
      'uid',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'promote',
      'sticky',
      'default_langcode',
      'revision_default',
      'revision_translation_affected',
    ];

    $data = [];

    foreach ($entity->getFields() as $field) {
      if (in_array($field->getName(), $fields_to_exclude)) {
        continue;
      }

      // Track private fields.
      if ($field->getFieldDefinition()->getConfig($entity->bundle())->getThirdPartySetting('docstore', 'private', FALSE)) {
        $data['_metadata']['private_fields'][] = $field->getName();
      }

      switch ($field->getFieldDefinition()->getType()) {
        // Process boolean fields, ensuring they are booleans.
        case 'boolean':
          $data[$field->getName()] = $this->prepareSearchResultBooleanField($field);
          break;

        // Process date fields, converting them to ISO 8601 dates.
        case 'timestamp':
        case 'datetime':
        case 'created':
        case 'changed':
          $data[$field->getName()] = $this->prepareSearchResultDateField($field);
          break;

        case 'daterange':
          $data[$field->getName()] = $this->prepareSearchResultDateRangeField($field);
          break;

        // Process link fields, extracting the uri and title.
        case 'link':
          $data[$field->getName()] = $this->prepareSearchResultLinkField($field);
          break;

        // Process entity reference, extracting the uuid and label.
        case 'node_reference':
        case 'term_reference':
        case 'entity_reference':
        case 'entity_reference_uuid':
          if ($field->getName() === 'type') {
            $data[$field->getName()] = $field->entity->label();
          }
          elseif ($field->getName() === 'files') {
            $data[$field->getName()] = $this->prepareSearchResultFilesField($field);
          }
          else {
            $data[$field->getName()] = $this->prepareSearchResultEntityReferenceField($field);
          }
          break;

        default:
          $data[$field->getName()] = $field->value;
          break;
      }
    }

    $document_fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'document_field');

    foreach ($document_fields as $document_field) {
      $document_field->addValue(serialize($data));
    }
  }

  /**
   * Prepare the data for a boolean field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Item field.
   *
   * @return array
   *   List of values all converted to booleans.
   */
  public function prepareSearchResultBooleanField(FieldItemListInterface $field) {
    if ($field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()) {
      $data = [];
      foreach ($field as $value) {
        $data[] = !empty($value);
      }
      return $data;
    }
    else {
      return !empty($field->value);
    }
  }

  /**
   * Prepare the data for a date like field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Item field.
   *
   * @return array
   *   List of values all converted to ISO 8601 dates.
   */
  public function prepareSearchResultDateField(FieldItemListInterface $field) {
    if ($field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()) {
      $data = [];
      foreach ($field->getValue() as $value) {
        $value = $this->formatIso8601Date($value);
        if (!empty($value)) {
          $data[] = $value;
        }
      }
      return $data;
    }
    else {
      return $this->formatIso8601Date($field->value);
    }
  }

  /**
   * Prepare the data for a date range field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Item field.
   *
   * @return array
   *   List of values all converted to ISO 8601 dates.
   */
  public function prepareSearchResultDateRangeField(FieldItemListInterface $field) {
    if ($field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()) {
      $data = [];
      foreach ($field as $value) {
        if (!empty($value)) {
          $data[] = [
            'start' => $this->formatIso8601Date($value->value),
            'end' => $this->formatIso8601Date($value->end_value),
          ];
        }
      }
      return $data;
    }
    else {
      return [
        'start' => $this->formatIso8601Date($field->value),
        'end' => $this->formatIso8601Date($field->end_value),
      ];
    }
  }

  /**
   * Prepare the data for a link field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field.
   *
   * @return array
   *   List of values with link uri and title.
   */
  public function prepareSearchResultLinkField(FieldItemListInterface $field) {
    $data = [];
    foreach ($field as $value) {
      $data[] = [
        'uri' => $value->uri,
        'title' => $value->title,
      ];
    }

    if ($field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()) {
      return $data;
    }
    else {
      return reset($data);
    }
  }

  /**
   * Prepare the data for the files field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Field.
   *
   * @return array
   *   List of referenced entities with their uuid and label.
   */
  public function prepareSearchResultFilesField(FieldItemListInterface $field) {
    $data = [];
    foreach ($field as $value) {
      $uri = $value->entity->field_media_file->entity->getFileUri();
      $data[] = [
        'media_uuid' => $value->entity->uuid(),
        'file_uuid' => $value->entity->field_media_file->entity->uuid(),
        'filename' => $value->entity->getName(),
        'created' => $this->formatIso8601Date($value->entity->created->value),
        'changed' => $this->formatIso8601Date($value->entity->changed->value),
        'mimetype' => $value->entity->field_media_file->entity->getMimeType(),
        'size' => $value->entity->field_media_file->entity->getSize(),
        'uri' => static::createFileUrl($uri),
        'private' => $this->uriIsPrivate($uri),
      ];
    }

    return $data;
  }

  /**
   * Prepare the data for an entity reference field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Field.
   *
   * @return array
   *   List of referenced entities with their uuid and label.
   */
  public function prepareSearchResultEntityReferenceField(FieldItemListInterface $field) {
    $data = [];
    foreach ($field as $value) {
      if ($value->entity->getEntityTypeId() === 'node') {
        $data[] = [
          'uuid' => $value->entity->uuid(),
          'label' => $value->entity->title->value,
        ];
      }
      else {
        $data[] = [
          'uuid' => $value->entity->uuid(),
          'name' => $value->entity->getName(),
        ];
      }
    }

    if ($field->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()) {
      return $data;
    }
    else {
      return reset($data);
    }
  }

}
