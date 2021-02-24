<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api\Entity\Index;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Trait for searchable/filterable resources.
 */
trait SearchableResourceTrait {

  use ProviderTrait;
  use ResourceTrait;

  /**
   * Check if a request is a search request based on the parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return bool
   *   TRUE of the request is a search/filter request.
   */
  public function isSearchRequest(Request $request) {
    // @todo any more parameters?
    $search_parameters = ['filter', 'sort', 's', 'search', 'page'];
    foreach ($search_parameters as $parameter) {
      if ($request->query->has($parameter)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Search Solr for resources matching the request parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   * @param string $entity_type_id
   *   Entity type of the resource (ex: node, taxonomy_term).
   * @param \Drupal\Core\Config\Entity\ConfigEntityBundleBase|null $bundle_entity
   *   Bundle entity for the resource. If not defined, then search against all
   *   resources of the given type.
   * @param string $id
   *   If provided, retrieve the document with the given id or uuid.
   * @param bool $with_revisions
   *   If a id is provided and with_revisions is set to TRUE, then add the
   *   list of revisions to the returned resource.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the found resources.
   */
  public function searchResources(Request $request, $entity_type_id, ?ConfigEntityBundleBase $bundle_entity = NULL, $id = NULL, $with_revisions = FALSE) {
    // Load the search API index.
    $index = Index::load($this->getResourceType($entity_type_id));
    if (empty($index)) {
      throw new BadRequestHttpException('The endpoint does not accept filter/search requests');
    }

    // Extract information about the given entity type.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_type = $storage->getEntityType();
    $bundle_type_id = $entity_type->getBundleEntityType();

    // Get the entity bundle if available.
    $bundle = isset($bundle_entity) ? $bundle_entity->id() : NULL;

    // Get the solr backend.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();

    // Make sure backend is solr.
    if (!($solr instanceof SolrBackendInterface)) {
      // Throw a 500 Internal Server Error.
      throw new HttpException(500, 'Only solr backend is supported');
    }

    // Get the provider.
    $provider = $this->getProvider();

    // Get the list of resources on which to perform the search.
    // If bundle is defined and accessible, then the list will only contain it.
    $accessible_resource_types = $this->getAccessibleResourceTypes($bundle_type_id, $provider, $bundle);

    // Set the default cache.
    $cache = $this->createResponseCache()->addCacheTags([
      $this->getResourceType($entity_type_id),
    ]);

    if (isset($bundle_entity)) {
      $cache->addCacheableDependency($bundle_entity);
    }

    /** @var \Drupal\search_api\Query\QueryInterface $query */
    $query = $index->query();

    // If id is defined, this is query for a single resource.
    if (isset($id)) {
      if (Uuid::isValid($id)) {
        $query->addCondition($entity_type->getKey('uuid'), $id);
      }
      else {
        $query->addCondition($entity_type->getKey('id'), $id);
      }
    }
    // Otherwise we parse the request parameters to filter/sort etc. the query.
    else {
      $this->parseSearchParameters($request, $query, $cache);
    }

    // Filter the type of resources this query is against.
    $query->addCondition($entity_type->getKey('bundle'), $accessible_resource_types, 'IN');

    // For anonymous requests, limit to published public resources.
    if ($provider->isAnonymous()) {
      $query->addCondition('published', TRUE);
      if ($index->getField('private') !== NULL) {
        $query->addCondition('private', TRUE, '<>');
      }
    }
    // Otherwise allow published private resources accessible to the provider
    // in addition to published public resources.
    else {
      // Return private resources of provider.
      $group_provider = $query->createConditionGroup('OR');
      $group_provider->addCondition('provider_uuid', $provider->uuid());

      // Or public published resources.
      $group_published = $query->createConditionGroup('AND');
      $group_published->addCondition('published', TRUE);
      if ($index->getField('private') !== NULL) {
        $group_published->addCondition('private', TRUE, '<>');
      }

      $group_provider->addConditionGroup($group_published);
      $query->addConditionGroup($group_provider);
    }

    // Add a default sort if not was provided.
    $sorts = $query->getSorts();
    if (empty($sorts)) {
      // Default to the creation date in the document store. returning the
      // most recent resources first.
      if ($index->getField('created') !== NULL) {
        $query->sort('created', 'DESC');
      }
      // Otherwise sort by label.
      else {
        $query->sort($entity_type->getKey('label'), 'ASC');
      }
    }

    // Run the search API query.
    //
    // The resulset is built when executing the query and contains a list of
    // items with data extracted from the solr response without, initially,
    // data load from the the database, improving performances.
    //
    // @see Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend::search()
    // @see Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend::extractResults()
    try {
      /** @var \Drupal\search_api\Query\ResultSetInterface $results */
      $results = $query->execute();
    }
    // @todo better handle the exception to avoid showing internals.
    catch (SearchApiSolrException $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Add cache info from the query.
    $cache->addCacheableDependency($query);

    // Prepare the data to return.
    $data = $this->prepareResultSetData($results, $provider);

    // Throw a 404 Not Found if no resource was found for the given id.
    // Cache it to avoid doing another query until something changes for
    // this resource.
    if (isset($id)) {
      if (empty($data)) {
        throw new CacheableNotFoundHttpException($cache, strtr('@label @id does not exist', [
          '@label' => $this->getResourceTypeLabel($entity_type_id, FALSE),
          '@id' => $id,
        ]));
      }
      else {
        $data = reset($data);
      }

      // Add the revisions to the resource data.
      if ($with_revisions) {
        $data['revisions'] = $this->getResourceEntityRevisionList($entity_type_id, $id);
      }
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Add the search parameters from the request to the search api query.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cache metadata.
   */
  public function parseSearchParameters(Request $request, QueryInterface $query, CacheableMetadata $cache) {
    $parser = new ParseQueryParameters();
    $index = $query->getIndex();

    // Parse filter parameter.
    // @todo we should not allow filtering on private fields, so somehow we
    // need to get the list of fields in the filters, check against
    // MetadataTrait::getReadableFields() and throw an error when attempting
    // to filter by a non accessible field.
    if ($request->query->has('filter')) {
      $filters = $parser->parseFilters($request->query->get('filter'));
      $parser->applyFiltersToIndex($filters, $query);
    }

    // Parse sort parameter.
    if ($request->query->has('sort')) {
      $sorters = $parser->parseSort($request->query->get('sort'));
      $parser->applySortToIndex($sorters, $query);
    }

    // Parse pagination parameter.
    if ($request->query->has('page')) {
      $page = $request->query->get('page');
    }
    // If not defined, build one based on the limit and offset parameters if
    // defined.
    else {
      $page = [
        $parser::OFFSET_KEY => $request->query->getInt('offset', $parser::DEFAULT_OFFSET),
        $parser::SIZE_KEY => $request->query->getInt('limit', $parser::SIZE_MAX),
      ];
    }
    $pagers = $parser->parsePaging($page);
    $parser->applyPagerToIndex($pagers, $query);

    // Parse full text search parameter (either `s` or `search`).
    // @todo allow to pass fields in which to search?
    $search = $request->query->get('s', $request->query->get('search', ''));
    if (!empty($search)) {
      $full_text_search_fields = $index->getFulltextFields();
      if (empty($full_text_search_fields)) {
        throw new BadRequestHttpException('This resource does not support full text search');
      }
      $query->setFulltextFields($full_text_search_fields);
      $query->keys($search);
    }

    // Add the cache contexts for the request parameters.
    $cache->addCacheContexts([
      'url.query_args:filter',
      'url.query_args:sort',
      'url.query_args:page',
      'url.query_args:limit',
      'url.query_args:offset',
      'url.query_args:s',
      'url.query_args:search',
    ]);
  }

  /**
   * Prepare the data from the search result for the response.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   Result set with the resources that matched the search query.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   List of resources that matched the search query.
   */
  public function prepareResultSetData(ResultSetInterface $results, UserInterface $provider) {
    $data = [];

    foreach ($results as $item) {
      // Get the entity type of the item.
      $entity_type_id = $item->getDataSource()->getEntityTypeId();

      // Skip if we cannot retrieve the entity type for the item as we cannot
      // process it.
      if (empty($entity_type_id)) {
        continue;
      }

      // Get the entity keys for the entity type.
      $entity_keys = $this->entityTypeManager
        ->getStorage($entity_type_id)
        ->getEntityType()
        ->getKeys();

      // Pass FALSE to only use the data returned by Solr, for performances.
      /** @var \Drupal\search_api\Item\FieldInterface[] $fields */
      $fields = $item->getFields(FALSE);

      // Skip if we cannot determine the bundle of the item as we cannot
      // process it.
      if (!isset($entity_keys['bundle'], $fields[$entity_keys['bundle']])) {
        continue;
      }
      $bundles = $fields[$entity_keys['bundle']]->getValues();
      if (empty($bundles)) {
        continue;
      }
      $bundle = reset($bundles);
      if (empty($bundle)) {
        continue;
      }

      // Special handling of the published field (used for filtering) which may
      // have a different name.
      if (isset($fields['published'], $entity_keys['published'])) {
        $fields[$entity_keys['published']] = $fields['published'];
        unset($fields['published']);
      }

      // Process the fields for the result item.
      $item_data = $this->prepareResultItemData($entity_type_id, $bundle, $provider, $fields);
      if (!empty($item_data)) {
        $data[] = $item_data;
      }
    }

    return $data;
  }

  /**
   * Prepare the data for a search result item for the response.
   *
   * This is supposed to return the same data as when loading an entity
   * from the database and preparing its data for output but using the data
   * returned by Solr.
   *
   * @param string $entity_type_id
   *   Entity type id of the item (ex: node, taxonomy_term).
   * @param string $bundle
   *   Entity bundle of the item.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param \Drupal\search_api\Item\FieldInterface[] $fields
   *   Result item fields.
   *
   * @return array
   *   List of processed field values keyed by field name.
   *
   * @see \Drupal\docstore\ResourceTrait::prepareEntityResourceData()
   */
  public function prepareResultItemData($entity_type_id, $bundle, UserInterface $provider, array $fields) {
    $data = [];

    // Get the list of readable fields for this bundle.
    $readable_fields = $this->getReadableFields($entity_type_id, $bundle, $provider);

    // Sort the fields by key, this will put base field like `myfield` before
    // additional property fields like `myfield_label` and simplify the field
    // data handling.
    ksort($fields);

    // Prepare the fields.
    foreach ($fields as $name => $field) {
      // Skip fields that were removed.
      if (!isset($fields[$name])) {
        continue;
      }

      $field_data = [];

      // Remove the standard field type prefix.
      $field_type = str_replace('field_item:', '', $field->getOriginalType());

      // Special handling of geofield for which the `latlon` property is indexed
      // rather than the base field.
      // @see \Drupal\docstore\ManageFields::addFieldToIndex()
      if (substr_compare($name, '_latlon_', -8) === 0) {
        $name = substr($name, 0, -8);
        $field_type = 'geofield';
      }

      // Skip if the field is not readable.
      if (!isset($readable_fields[$name])) {
        continue;
      }
      $output_field_name = $readable_fields[$name];

      // Parse search result field.
      switch ($field_type) {
        // Process boolean fields, ensuring they are booleans.
        case 'boolean':
          $field_data = $this->prepareSearchResultBooleanField($field);
          break;

        // Process date fields, converting them to ISO 8601 dates.
        case 'timestamp':
        case 'datetime':
        case 'created':
        case 'changed':
          $field_data = $this->prepareSearchResultDateField($field);
          break;

        // Process daterange fields, extracting the start and end dates and
        // converting them to ISO 8601 dates.
        case 'datarange':
          $field_data = $this->prepareSearchResultDateRangeField($field, $name, $fields);
          break;

        // Process link fields, extracting the uri and title.
        case 'link':
          $field_data = $this->prepareSearchResultLinkField($field, $name, $fields);
          break;

        // Process geofield, extracting lat and lon properties.
        case 'geofield':
          $field_data = $this->prepareSearchResultGeofieldField($field);
          break;

        // Process entity reference, extracting the uuid and label.
        case 'node_reference':
        case 'term_reference':
        case 'entity_reference':
        case 'entity_reference_uuid':
          if ($name === 'files') {
            $field_data = $this->prepareSearchResultFilesField($field, $name, $field_type, $fields, $provider);
          }
          else {
            $field_data = $this->prepareSearchResultEntityReferenceField($field, $name, $field_type, $fields);
          }
          break;

        // For other fields, simply copy the values.
        default:
          $field_data = $field->getValues();
      }

      // Skip if there is no data for the field.
      if (empty($field_data)) {
        continue;
      }

      // Check if the field accept multiple values.
      $multiple = $this->isSearchResultFieldMultiple($field, $name, $field_type, $entity_type_id);

      // Convert field value based on cardinality.
      if (empty($multiple)) {
        $data[$output_field_name] = reset($field_data);
      }
      else {
        $data[$output_field_name] = array_values($field_data);
      }
    }

    // Update the data based on the entity type.
    $this->massageResourceDataForEntityType($data, $entity_type_id, $bundle);

    return $data;
  }

  /**
   * Prepare the data for a boolean field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search result item field.
   *
   * @return array
   *   List of values all converted to booleans.
   */
  public function prepareSearchResultBooleanField(FieldInterface $field) {
    $data = [];
    foreach ($field->getValues() as $value) {
      $data[] = !empty($value);
    }
    return $data;
  }

  /**
   * Prepare the data for a date like field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search result item field.
   *
   * @return array
   *   List of values all converted to ISO 8601 dates.
   */
  public function prepareSearchResultDateField(FieldInterface $field) {
    $data = [];
    foreach ($field->getValues() as $value) {
      $value = $this->formatIso8601Date($value);
      if (!empty($value)) {
        $data[] = $value;
      }
    }
    return $data;
  }

  /**
   * Prepare the data for a date range field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search result item field.
   * @param string $name
   *   Field name.
   * @param array $fields
   *   List of search result item fields. It will be used to look for the end
   *   date values.
   *
   * @return array
   *   List of values all converted to ISO 8601 dates.
   */
  public function prepareSearchResultDateRangeField(FieldInterface $field, $name, array &$fields) {
    $data = [];

    // Retrieve the end values.
    $end_values = $this->extractSearchResultAdditionalFieldValues($name . '_end_', $fields);

    // Parse the start values and add the corresponding end one if found.
    foreach ($field->getValues() as $key => $value) {
      $date = [];

      $start = $this->formatIso8601Date($value);
      if (!empty($start)) {
        $date['start'] = $start;
      }

      if (isset($end_values[$key])) {
        $end = $this->formatIso8601Date($end_values[$key]);
        if (!empty($end)) {
          $date['end'] = $end;
        }
      }

      if (!empty($date)) {
        $data[] = $date;
      }
    }

    return $data;
  }

  /**
   * Prepare the data for a geofield field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search result item field.
   *
   * @return array
   *   List of values with lat and lon.
   */
  public function prepareSearchResultGeofieldField(FieldInterface $field) {
    $data = [];
    foreach ($field->getValues() as $value) {
      // The value contains the lat and lon separated by a comma.
      if (strpos($value, ',') !== FALSE) {
        list($lat, $lon) = explode(',', $value);
        $data[] = [
          'lat' => $lat,
          'lon' => $lon,
        ];
      }
    }
    return $data;
  }

  /**
   * Prepare the data for a link field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search result item field.
   * @param string $name
   *   Field name.
   * @param array $fields
   *   List of search result item fields. It will be used to look for the end
   *   date values.
   *
   * @return array
   *   List of values with link uri and title.
   */
  public function prepareSearchResultLinkField(FieldInterface $field, $name, array &$fields) {
    $data = [];

    // Retrieve the link title values.
    $title_values = $this->extractSearchResultAdditionalFieldValues($name . '_title_', $fields);

    // Parse the uri values and add the corresponding title value if found.
    foreach ($field->getValues() as $key => $value) {
      $link = ['uri' => $value];
      if (isset($title_values[$key])) {
        $link['title'] = $title_values[$key];
      }
      $data[] = $link;
    }

    return $data;
  }

  /**
   * Prepare the data for the files field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search result item field.
   * @param string $name
   *   Field name.
   * @param string $type
   *   Field type. It's used to determine whether the values correspond
   *   to entity uuids or ids in which case we need to load the target entity
   *   to retrieve its uuid.
   * @param array $fields
   *   List of search result item fields. It will be used to look for the end
   *   date values.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   List of referenced entities with their uuid and label.
   */
  public function prepareSearchResultFilesField(FieldInterface $field, $name, $type, array &$fields, UserInterface $provider) {
    $data = [];

    // Retrieve the media provider ids.
    $provider_ids = $this->extractSearchResultAdditionalFieldValues($name . '_media_uid_', $fields);

    // Retrieve the uri extra field.
    $uris = $this->extractSearchResultAdditionalFieldValues($name . '_file_uri_', $fields);

    // Retrieve the extra file properties.
    $extra_fields = [
      'filename' => $this->extractSearchResultAdditionalFieldValues($name . '_media_name_', $fields),
      'created' => $this->extractSearchResultAdditionalFieldValues($name . '_media_created_', $fields),
      'changed' => $this->extractSearchResultAdditionalFieldValues($name . '_media_changed_', $fields),
      'mimetype' => $this->extractSearchResultAdditionalFieldValues($name . '_file_filemime_', $fields),
      'size' => $this->extractSearchResultAdditionalFieldValues($name . '_field_filesize_', $fields),
      'file_uuid' => $this->extractSearchResultAdditionalFieldValues($name . '_file_uuid_', $fields),
    ];

    // Parse the id/uuid values and add the corresponding label field if found.
    foreach ($field->getValues() as $key => $value) {
      $file = ['private' => TRUE];

      // The file field is a `entity_reference_uuid` so the value is a uuid.
      // @see \Drupal\docstore\ManageFields::createDocumentBaseFieldFiles().
      $file['media_uuid'] = $value;

      // Add the extra properties.
      foreach ($extra_fields as $extra_field_name => $extra_field_values) {
        if (isset($extra_field_values[$key])) {
          $file[$extra_field_name] = $extra_field_values[$key];
        }
      }

      // Format the dates.
      if (isset($file['created'])) {
        $file['created'] = $this->formatIso8601Date($file['created']);
      }
      if (isset($file['changed'])) {
        $file['changed'] = $this->formatIso8601Date($file['changed']);
      }

      // Add the file url.
      if (!empty($uris[$key])) {
        $uri = $uris[$key];

        // For public files, generate the drupal url.
        if (!$this->uriIsPrivate($uri)) {
          $file['uri'] = static::createFileUrl($uri);
          $file['private'] = FALSE;
        }
        // For private files, if the provider is the owner of the media,
        // generate the direct url.
        // Note: no strict equality for ids as they can be strings or ints.
        elseif (!$provider->isAnonymous() & isset($provider_ids[$key]) && $provider_ids[$key] == $provider->id() && isset($file['filename'])) {
          $file['uri'] = $this->createDirectUrl('media', $file['uuid'], $file['filename'], $provider);
        }
      }

      $data[] = $file;
    }

    return $data;
  }

  /**
   * Prepare the data for an entity reference field.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search result item field.
   * @param string $name
   *   Field name.
   * @param string $type
   *   Field type. It's used to determine whether the values correspond
   *   to entity uuids or ids in which case we need to load the target entity
   *   to retrieve its uuid.
   * @param array $fields
   *   List of search result item fields. It will be used to look for the end
   *   date values.
   *
   * @return array
   *   List of referenced entities with their uuid and label.
   */
  public function prepareSearchResultEntityReferenceField(FieldInterface $field, $name, $type, array &$fields) {
    $data = [];

    // Retrieve the reference label values.
    $label_values = $this->extractSearchResultAdditionalFieldValues($name . '_label_', $fields);

    // Get the type of entity referenced by this field.
    $target_entity_type_id = $field
      ->getDataDefinition()
      ->getSetting('target_type');

    // Get the storage for the target entity type.
    $storage = $this->entityTypeManager
      ->getStorage($target_entity_type_id);

    // Get the label field name for the target entity type.
    $label_key = $storage
      ->getEntityType()
      ->getKey('label');

    // Parse the id/uuid values and add the corresponding label field if found.
    foreach ($field->getValues() as $key => $value) {
      $reference = [];

      if ($type === 'entity_reference_uuid') {
        $reference['uuid'] = $value;
      }
      else {
        // We need to load the entity to retrieve the uuid.
        $target_entity = $storage->load($value);
        // Skip if we couldn't find the corresponding entity.
        if (empty($target_entity)) {
          continue;
        }
        $reference['uuid'] = $target_entity->uuid();
      }

      if (isset($label_values[$key])) {
        $reference[$label_key] = $label_values[$key];
      }

      $data[] = $reference;
    }

    return $data;
  }

  /**
   * Get the values of an additional field (ex: link title).
   *
   * @param string $name
   *   Name of the additional field.
   * @param array $fields
   *   List of a search result item fields.
   *
   * @return array
   *   Extracted values.
   */
  public function extractSearchResultAdditionalFieldValues($name, array &$fields) {
    $values = [];
    if (isset($fields[$name])) {
      $values = $fields[$name]->getValues();
      unset($fields[$name]);
    }
    return $values;
  }

  /**
   * Check if a search result field can have multitple values.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   Search result item field.
   * @param string $name
   *   Field name.
   * @param string $type
   *   Field type.
   * @param string $entity_type_id
   *   Type of the entity the field is defined on.
   *
   * @return bool
   *   TRUE if the field can have multiple values.
   */
  public function isSearchResultFieldMultiple(FieldInterface $field, $name, $type, $entity_type_id) {
    $multiple = FALSE;

    // For geofield, as we index the sub proprety (latlon) that has a
    // cardinality of 1 (within the field), we need to retrieve the field
    // definition of the base field to check whether the field accepts
    // multiple values or not.
    if ($type === 'geofield') {
      $field_storage_definitions = $this->entityFieldManager
        ->getFieldStorageDefinitions($entity_type_id);
      if (isset($field_storage_definitions[$name])) {
        $multiple = $field_storage_definitions[$name]->isMultiple();
      }
    }
    // Otherwise we retrieve the information from the field definition.
    else {
      // Check if the field accept multiple values.
      $field_data_definition = $field->getDataDefinition();
      if ($field_data_definition instanceof FieldItemDataDefinition) {
        $multiple = $field_data_definition
          ->getFieldDefinition()
          ->getFieldStorageDefinition()
          ->isMultiple();
      }
    }

    return $multiple;
  }

}
