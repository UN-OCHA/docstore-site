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
   * @param bool $files_only
   *   If TRUE, return only the list of files associated with the matching
   *   documents.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the found resources.
   */
  public function searchResources(Request $request, $entity_type_id, ?ConfigEntityBundleBase $bundle_entity = NULL, $id = NULL, $with_revisions = FALSE, $files_only = FALSE) {
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
      // Sort by ID descending which basically corresponds to sorting by
      // creation date descending.
      if ($index->getField($entity_type->getKey('id')) !== NULL) {
        $query->sort($entity_type->getKey('id'), 'DESC');
      }
      // Otherwise sort by label.
      elseif ($index->getField($entity_type->getKey('label')) !== NULL) {
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

    // If instructed so, only return the list of unique files associated with
    // the document(s).
    if (!empty($files_only)) {
      $files = [];
      foreach ($data as $item) {
        if (isset($item['files'])) {
          foreach ($item['files'] as $file) {
            $files[$file['media_uuid']] = $file;
          }
        }
      }
      $data = array_values($files);
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

    // Use stored data.
    $stored_data = [];
    if ($entity_type_id === 'node') {
      $stored_data = unserialize($fields['document']->getValues()[0]);
    }
    elseif ($entity_type_id === 'taxonomy_term') {
      $stored_data = unserialize($fields['terms']->getValues()[0]);
    }
    else {
      throw new BadRequestHttpException('Unknown entity');
    }

    foreach ($stored_data as $name => $field) {
      if (!isset($readable_fields[$name])) {
        continue;
      }
      $data[$readable_fields[$name]] = $field;
    }

    // Remove private files.
    if (isset($data['files']) && is_array($data['files'])) {
      foreach ($data['files'] as &$file) {
        if ($file['private']) {
          if ($provider->uuid() !== $file['provider_uuid']) {
            unset($file['uri']);
          }
        }
      }
    }

    // Update the data based on the entity type.
    $this->massageResourceDataForEntityType($data, $entity_type_id, $bundle);

    return $data;
  }

}
