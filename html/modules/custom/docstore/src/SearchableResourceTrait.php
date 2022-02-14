<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
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
   * @param bool $files_only
   *   If TRUE, return only the list of files associated with the matching
   *   documents.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the found resources.
   */
  public function searchResources(Request $request, $entity_type_id, ?ConfigEntityBundleBase $bundle_entity = NULL, $id = NULL, $with_revisions = FALSE, $files_only = FALSE) {
    static $retry_interval = 1;

    $option_list = FALSE;
    if (strpos($entity_type_id, '__option_list') !== FALSE) {
      $entity_type_id = str_replace('__option_list', '', $entity_type_id);
      $option_list = TRUE;
    }

    // Load the search API index.
    if ($entity_type_id === 'node') {
      if (is_null($bundle_entity)) {
        // @todo Search across all document indexes.
        throw new BadRequestHttpException('Any request not allowed');
      }
      else {
        $index = Index::load('documents_' . $bundle_entity->id());
      }
    }
    elseif ($entity_type_id === 'taxonomy_term') {
      if (is_null($bundle_entity)) {
        throw new BadRequestHttpException('Any request not allowed');
      }
      else {
        $index = Index::load('terms_' . $bundle_entity->id());
        // Fallback to generic index.
        if (empty($index)) {
          $index = Index::load('terms');
        }
      }
    }
    else {
      $index = Index::load($this->getResourceType($entity_type_id));
    }

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

    // Remove paging for option lists.
    if ($option_list) {
      $query->range(0, 9999);
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
      // Sort option list by label.
      if ($option_list) {
        $query->sort($entity_type->getKey('label'), 'ASC');
      }
      // Sort by ID descending which basically corresponds to sorting by
      // creation date descending.
      elseif ($index->getField($entity_type->getKey('id')) !== NULL) {
        $query->sort($entity_type->getKey('id'), 'DESC');
      }
      // Otherwise sort by label.
      elseif ($index->getField($entity_type->getKey('label')) !== NULL) {
        $query->sort($entity_type->getKey('label'), 'ASC');
      }
    }

    // Add facets.
    $enabled_facet_fields = $index->getThirdPartySetting('docstore', 'facets', []);
    if (!empty($enabled_facet_fields) && $query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      $enabled_facets = [];
      foreach ($enabled_facet_fields as $enabled_facet_field) {
        $enabled_facets[$enabled_facet_field] = [
          'field' => $enabled_facet_field,
          'limit' => 999,
          'missing' => FALSE,
          'operator' => 'AND',
          'min_count' => 1,
        ];
      }

      $query->setOption('search_api_facets', $enabled_facets);
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
      if ($retry_interval <= 4) {
        // Log it.
        if ($this->loggerFactory) {
          $this->loggerFactory->get('searchResources')->warning($exception->getMessage());
        }

        // Sleep on it.
        sleep($retry_interval);

        // Double the interval.
        $retry_interval *= 2;

        return $this->searchResources($request, $entity_type_id, $bundle_entity, $id, $with_revisions, $files_only);
      }

      // Fail hard.
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Reset retry interval.
    $retry_interval = 1;

    // Add cache info from the query.
    $cache->addCacheableDependency($query);

    // Prepare the data to return.
    $data = $this->prepareResultSetData($results, $provider);

    // We only need uuid, label and display_name.
    if ($option_list) {
      $keep = [
        'uuid',
        'label',
        'display_name',
      ];

      foreach ($data as &$row) {
        foreach ($row as $name => $value) {
          if (!in_array($name, $keep)) {
            unset($row[$name]);
          }
        }
      }
    }

    // If instructed so, only return the list of unique files associated with
    // the document(s).
    if (!empty($files_only)) {
      $files = [];
      foreach ($data as $item) {
        if (isset($item['files'])) {
          foreach ($item['files'] as $file) {
            $files[$file['uuid']] = $file;
          }
        }
      }

      $data = [
        '_count' => count($files),
        '_number_of_documents' => $results->getResultCount(),
        'results' => array_values($files),
      ];
    }
    // Throw a 404 Not Found if no resource was found for the given id.
    // Cache it to avoid doing another query until something changes for
    // this resource.
    elseif (isset($id)) {
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
    else {
      // Multiple results.
      $output = [
        '_count' => $results->getResultCount(),
      ];

      // Build facets.
      if (!empty($enabled_facet_fields) && $query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
        $facet_array = [];
        $solr_facets = $results->getExtraData('search_api_facets');
        if (is_array($solr_facets)) {
          foreach ($solr_facets as $field => $solr_facet) {
            $field_info = $index->getField($field);

            $facet_data = [
              'id' => $field,
              'label' => $field_info->getLabel(),
              'items' => [],
            ];

            foreach ($solr_facet as $facet_item) {
              $facet_uuids[] = trim(trim($facet_item['filter'], '"'));
            }

            $storage = $this->entityTypeManager->getStorage($field_info->getDataDefinition()->getSettings()['target_type']);
            $uuid_key = $storage->getEntityType()->getKey('uuid');
            if (!empty($uuid_key)) {
              $entity_ids = $storage->getQuery()->condition($uuid_key, $facet_uuids, 'IN')->execute();
              $entities = $storage->loadMultiple($entity_ids);
            }

            foreach ($solr_facets[$field] as $facet_info) {
              $facet_uuid = trim(trim($facet_info['filter'], '"'));

              foreach ($entities as $entity) {
                if ($entity->uuid() == $facet_uuid) {
                  $label = $entity->label();
                  if ($entity->hasField('display_name') && !$entity->display_name->isEmpty()) {
                    $label = $entity->display_name->value;
                  }
                  $facet_data['items'][$facet_uuid] = [
                    'filter' => $facet_uuid,
                    'label' => $label,
                    'count' => $facet_info['count'],
                  ];
                }
              }
            }

            $facet_array[] = $facet_data;
          }

          if (!empty($facet_array)) {
            $output['_facets'] = $facet_array;
          }
        }
      }

      // Add extra metadata.
      if ($extra = $results->getExtraData('search_api_solr_response')) {
        if (isset($extra['response']['start'])) {
          $output['_start'] = $extra['response']['start'];
        }
        if (isset($extra['response']['_numFoundExact'])) {
          $output['_numFoundExact'] = $extra['response']['numFoundExact'];
        }
      }

      // Append data.
      $output['results'] = $data;

      $data = $output;
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
      if (empty($entity_type_id)) {
        continue;
      }

      // Pass FALSE to only use the data returned by Solr, for performances.
      /** @var \Drupal\search_api\Item\FieldInterface $storage_field */
      $storage_field = $item->getField('_stored_entity_fields', FALSE);
      if (empty($storage_field)) {
        continue;
      }

      // Retrieve the stored data. It should be an array with the first item
      // being the serialized preprocessed entity data.
      $values = $storage_field->getValues();
      if (empty($values)) {
        continue;
      }

      // Unserialize the stored data.
      // @todo this is picked up with the message: "unserialize() is insecure
      // unless allowed classes are limited. Use a safe format like JSON or use
      // the allowed_classes option."
      // @codingStandardsIgnoreLine
      $stored_data = unserialize(base64_decode(reset($values)));

      // Skip if we cannot retrieve the resource's bundle.
      // @see \Drupal\docstore\ResourceTrait::massageResourceDataForEntityType()
      if (!isset($stored_data['bundle'])) {
        continue;
      }

      // Prepare the data for the response.
      $data[] = $this->massageResourceDataForResponse($stored_data, $entity_type_id, $stored_data['bundle'], $provider);
    }

    return $data;
  }

}
