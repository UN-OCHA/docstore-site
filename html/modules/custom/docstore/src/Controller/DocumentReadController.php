<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Drupal\docstore\DocumentTypeTrait;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ParseQueryParameters;
use Drupal\field\Entity\FieldConfig;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * Controller for API endpoints.
 */
class DocumentReadController extends ControllerBase {

  use DocumentTypeTrait;
  use ProviderTrait;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config,
      Connection $database,
      EntityTypeManagerInterface $entityTypeManager,
      LoggerChannelFactoryInterface $logger_factory,
      State $state
    ) {
    $this->config = $config;
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $logger_factory;
    $this->state = $state;
  }

  /**
   * Wait endpoint.
   */
  public function wait() {
    sleep(1);

    $response = new JsonResponse([]);
    return $response;
  }

  /**
   * Get documents.
   */
  public function getDocuments($type, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'read');

    // Check if provider has access.
    $provider = $this->getProvider();
    if ($node_type !== 'any' && !$this->providerCanRead($node_type, $provider)) {
      throw new AccessDeniedHttpException(strtr('You do not have access to read @type', [
        '@type' => $node_type,
      ]));
    }

    // Query index.
    $index = Index::load('documents');
    $query = $index->query();

    // Append filters.
    $parser = new ParseQueryParameters();
    if ($request->query->has('filter')) {
      $filters = $parser->parseFilters($request->query->get('filter'));
      $parser->applyFiltersToIndex($filters, $query);
    }
    if ($request->query->has('sort')) {
      $sorters = $parser->parseSort($request->query->get('sort'));
      $parser->applySortToIndex($sorters, $query);
    }
    else {
      // Add default sort.
      $query->sort('created', 'ASC');
    }
    if ($request->query->has('page')) {
      $pagers = $parser->parsePaging($request->query->get('page'));
      $parser->applyPagerToIndex($pagers, $query);
    }

    // Add full text search.
    if ($request->query->has('s')) {
      $query->setFulltextFields(['title', 'rendered_item']);
      $query->keys($request->query->get('s'));
    }

    // Add node_type if not any.
    if ($node_type !== 'any') {
      $query->addCondition('type', $node_type);
    }
    else {
      // Limit to accessible node types.
      $accessible_types = $this->getAccessibleDocumentTypes($provider);
      if (!empty($accessible_types)) {
        $query->addCondition('type', $accessible_types, 'IN');
      }
    }

    // Check published and private.
    if ($provider->isAnonymous()) {
      $query->addCondition('published', TRUE);
      $query->addCondition('private', TRUE, '<>');
    }
    else {
      // Return private documents of provider.
      $group_provider = $query->createConditionGroup('OR');
      $group_provider->addCondition('provider', $provider->uuid());

      // Or public published documents.
      $group_published = $query->createConditionGroup('AND');
      $group_published->addCondition('published', TRUE);
      $group_published->addCondition('private', TRUE, '<>');

      $group_provider->addConditionGroup($group_published);
      $query->addConditionGroup($group_provider);
    }

    try {
      // Execute.
      $results = $query->execute();
    }
    catch (SearchApiSolrException $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Use solr response directly.
    $solr_response = $results->getExtraData('search_api_solr_response', []);

    // Build output data.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();

    // Make sure backend is solr.
    if (!($solr instanceof SolrBackendInterface)) {
      throw new BadRequestHttpException('Only solr backend is supported');
    }

    // Massage the data.
    $data = $this->buildDocumentOutputFromSolr($solr_response['response']['docs'], $solr, $index, $request->getSchemeAndHttpHost(), $provider);

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'documents',
      ],
    ];

    // Add cache tags.
    foreach ($data as &$document) {
      $cache_tags['#cache']['tags'][] = $document['search_api_id'];
      unset($document['search_api_id']);
    }

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags)->addCacheContexts([
      'user',
      'url.query_args:filter',
      'url.query_args:sort',
      'url.query_args:page',
    ]));

    return $response;
  }

  /**
   * Build document output.
   */
  protected function buildDocumentOutputFromSolr($docs, $solr, $index, $base_url) {
    $data = [];

    // Load provider.
    $provider = $this->getProvider();

    $field_mapping = $solr->getSolrFieldNames($index);
    $language_field = $field_mapping['search_api_language'];

    foreach ($docs as $solr_row) {
      if ($language_field && isset($solr_row[$language_field])) {
        $language_id = $solr_row[$language_field];
        $field_mapping = $solr->getLanguageSpecificSolrFieldNames($language_id, $index);
      }

      $row = [];
      foreach ($field_mapping as $name => $solr_name) {
        if (isset($solr_row[$solr_name])) {
          $row[$name] = $solr_row[$solr_name];
        }
      }

      // Remove solr fields.
      if (isset($row['search_api_datasource'])) {
        unset($row['search_api_datasource']);
      }
      if (isset($row['search_api_language'])) {
        unset($row['search_api_language']);
      }
      if (isset($row['rendered_item'])) {
        unset($row['rendered_item']);
      }

      // Re-write file information.
      $row['files'] = [];
      if (isset($row['files_media_uuid'])) {
        foreach ($row['files_media_uuid'] as $key => $value) {
          $file_record = [
            'private' => FALSE,
            'media_uuid' => $value,
            'file_uuid' => $row['files_file_uuid'][$key] ?? '',
            'filename' => $row['files_file_filename'][$key] ?? '',
            'uri' => $base_url . $row['files_file_uri'][$key] ?? '',
            'mime' => $row['files_file_filemime'][$key] ?? '',
          ];

          // Hide private files, unless it's the owner.
          if (strpos($row['files_file_uri'][$key], '/system/files') === 0) {
            $file_record['private'] = TRUE;
            if ($provider->isAnonymous() || $row['provider'] !== $provider->uuid()) {
              unset($file_record['uri']);
            }
          }

          $row['files'][] = $file_record;
        }
      }

      // Remove files fields.
      foreach ($row as $key => $value) {
        if (strpos($key, 'files_') === 0) {
          unset($row[$key]);
        }
      }


      // Output tags as objects.
      foreach ($row as $key => $row_data) {
        // Make sure field still exists.
        if (!isset($row[$key])) {
          continue;
        }

        // Check if it's a special label field.
        if (strpos($key, '_label') !== FALSE && isset($row[str_replace('_label', '', $key)])) {
          // Will be checked when checking key without _label.
        }
        // Check if it's a link title field.
        if (strpos($key, '_title') !== FALSE && isset($row[str_replace('_title', '', $key)])) {
          // Will be checked when checking key without _title.
        }
        // Check if it's a date field.
        elseif (strpos($key, '_end') !== FALSE && isset($row[str_replace('_end', '', $key)])) {
          // Will be checked when checking key without _end.
        }
        // Check if it's a geofield field.
        elseif (strpos($key, '_lat') !== FALSE && isset($row[str_replace('_lat', '', $key)])) {
          // Will be checked when checking key without _lat.
        }
        elseif (strpos($key, '_lon') !== FALSE && isset($row[str_replace('_lon', '', $key)])) {
          // Will be checked when checking key without _lon.
        }
        elseif (strpos($key, '_latlon') !== FALSE) {
          if (isset($row[str_replace('_latlon', '', $key)])) {
            // Check without _latlon.
            if (!$this->providerCanUseDocumentField(str_replace('_latlon', '', $key), $row['type'], $provider)) {
              unset($row[$key]);
            }
          }
        }
        else {
          // Check if provider has access to the field.
          if (!$this->providerCanUseDocumentField($key, $row['type'], $provider)) {
            unset($row[$key]);

            if (isset($row[$key . '_label'])) {
              unset($row[$key . '_label']);
            }

            if (isset($row[$key . '_end'])) {
              unset($row[$key . '_end']);
            }

            continue;
          }
        }

        // Handle label fields.
        if (isset($row[$key . '_label'])) {
          if (is_array($row[$key])) {
            $tupples = [];
            foreach ($row_data as $tupple_key => $tupple_value) {
              $tupples[$tupple_key] = [
                'uuid' => $tupple_value,
                'name' => is_array($row[$key . '_label']) ? $row[$key . '_label'][$tupple_key] : $row[$key . '_label'],
              ];
            }
            $row[$key] = $tupples;
          }
          else {
            $row[$key] = [
              'uuid' => $row[$key],
              'name' => $row[$key . '_label'],
            ];
          }
          unset($row[$key . '_label']);
        }

        // Handle link title field.
        if (isset($row[$key . '_title'])) {
          if (is_array($row[$key . '_title'])) {
            $tupples = [];
            foreach ($row_data as $tupple_key => $tupple_value) {
              $tupples[$tupple_key] = [
                'uri' => $tupple_value,
                'title' => is_array($row[$key . '_title']) ? $row[$key . '_title'][$tupple_key] : $row[$key . '_title'],
              ];
            }
            $row[$key] = $tupples;
          }
          else {
            $row[$key] = [
              'uri' => $row[$key],
              'title' => $row[$key . '_title'],
            ];
          }
          unset($row[$key . '_title']);
        }
      }

      $data[] = $row;
    }

    return $data;
  }

  /**
   * Get document.
   */
  public function getDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'read');

    // Check if provider has access.
    $provider = $this->getProvider();
    if (!$this->providerCanRead($node_type, $provider)) {
      throw new AccessDeniedHttpException(strtr('You do not have access to read @type', [
        '@type' => $node_type,
      ]));
    }

    // Query index.
    $index = Index::load('documents');
    $query = $index->query();
    $query->addCondition('uuid', $id);

    // Add node_type if not any.
    if ($node_type !== 'any') {
      $query->addCondition('type', $node_type);
    }

    // Check published and private.
    $provider = $this->getProvider();
    if ($provider->isAnonymous()) {
      $query->addCondition('published', TRUE);
      $query->addCondition('private', TRUE, '<>');
    }
    else {
      // Return private documents of provider.
      $group_provider = $query->createConditionGroup('OR');
      $group_provider->addCondition('provider', $provider->uuid());
      // Or public published documents.
      $group_published = $query->createConditionGroup('AND');
      $group_published->addCondition('published', TRUE);
      $group_published->addCondition('private', TRUE, '<>');

      $group_provider->addConditionGroup($group_published);
      $query->addConditionGroup($group_provider);
    }

    $results = $query->execute();

    // Use solr response directly.
    $solr_response = $results->getExtraData('search_api_solr_response', []);

    // Build output data.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();

    // Make sure backend is solr.
    if (!($solr instanceof SolrBackendInterface)) {
      throw new BadRequestHttpException('Only solr backend is supported');
    }

    $data = $this->buildDocumentOutputFromSolr($solr_response['response']['docs'], $solr, $index, $request->getSchemeAndHttpHost(), $provider);

    if (empty($data)) {
      throw new NotFoundHttpException(strtr('Document @uuid does not exist', ['@uuid' => $id]));
    }

    $data = reset($data);

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'documents',
        $data['search_api_id'],
      ],
    ];
    unset($data['search_api_id']);

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags)->addCacheContexts([
      'user',
    ]));

    return $response;
  }

  /**
   * Get document revisions.
   */
  public function getDocumentRevisions($type, $id, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'read');

    $data = [];

    // Query index.
    $index = Index::load('documents');
    $query = $index->query();

    // Add node_type if not any.
    if ($node_type !== 'any') {
      $query->addCondition('type', $node_type);
    }

    $query->addCondition('uuid', $id);
    $results = $query->execute();

    // Check published and private.
    $provider = $this->getProvider();
    if ($provider->isAnonymous()) {
      $query->addCondition('published', TRUE);
      $query->addCondition('private', TRUE, '<>');
    }
    else {
      // Return private documents of provider.
      $group_provider = $query->createConditionGroup('OR');
      $group_provider->addCondition('provider', $provider->uuid());
      // Or public published documents.
      $group_published = $query->createConditionGroup('AND');
      $group_published->addCondition('published', TRUE);
      $group_published->addCondition('private', TRUE, '<>');

      $group_provider->addConditionGroup($group_published);
      $query->addConditionGroup($group_provider);
    }

    // Use solr response directly.
    $solr_response = $results->getExtraData('search_api_solr_response', []);

    // Build output data.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();

    // Make sure backend is solr.
    if (!($solr instanceof SolrBackendInterface)) {
      throw new BadRequestHttpException('Only solr backend is supported');
    }

    $data = $this->buildDocumentOutputFromSolr($solr_response['response']['docs'], $solr, $index, $request->getSchemeAndHttpHost());

    if (empty($data)) {
      throw new NotFoundHttpException(strtr('Document @uuid does not exist', ['@uuid' => $id]));
    }

    // Get first item.
    $data = reset($data);

    // Get all revisions.
    $query = $this->database->select('node_revision', 'nr')
      ->fields('nr', [
        'vid',
        'revision_timestamp',
        'revision_default',
        'revision_uid',
        'revision_log',
      ]);
    $query->innerJoin('node', 'n', 'nr.nid = n.nid');
    $revisions = $query->condition('n.uuid', $id)
      ->orderBy('vid', 'DESC')
      ->execute()
      ->fetchAll();

    $data['revisions'] = $revisions;

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'documents',
        $data['search_api_id'],
      ],
    ];
    unset($data['search_api_id']);

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Get 1 document revision.
   */
  public function getDocumentRevision($type, $id, $vid, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'read');

    // Check for last.
    if ($vid === 'last') {
      // Get last revisions.
      $query = $this->database->select('node_revision', 'nr')
        ->fields('nr', ['vid']);
      $query->innerJoin('node', 'n', 'nr.nid = n.nid');
      $vid = $query->condition('n.uuid', $id)
        ->orderBy('vid', 'DESC')
        ->execute()
        ->fetchCol(0);

      $vid = reset($vid);
    }

    /** @var \Drupal\node\Entity\Node $document */
    $document = $this->entityTypeManager->getStorage('node')->loadRevision($vid);
    if ($document->uuid() !== $id) {
      throw new BadRequestHttpException('Revision not found');
    }

    if ($document->bundle() !== $node_type) {
      throw new BadRequestHttpException('Wrong node type');
    }

    $data = [];
    $document_fields = $document->getFields(TRUE);
    foreach ($document_fields as $document_field) {
      $field_item_list = isset($document->{$document_field->getName()}) ? $document->{$document_field->getName()} : NULL;
      $field_item_definition = $field_item_list->getFieldDefinition();

      $values = [];
      foreach ($field_item_list as $field_item) {
        if ($main_property_name = $field_item->mainPropertyName()) {
          $values[] = $field_item->getValue()[$main_property_name];
        }
        else {
          $values[] = $field_item->getValue();
        }
      }

      if ($field_item_definition->getFieldStorageDefinition()->getCardinality() == 1) {
        $data[$document_field->getName()] = reset($values);
      }
      else {
        $data[$document_field->getName()] = $values;
      }
    }

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'documents',
      ] + $document->getCacheTags(),
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Get document files.
   */
  public function getDocumentFiles($type, $id, Request $request) {
    // Check if type is allowed.
    $this->typeAllowed($type, 'read');

    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get document terms.
   */
  public function getDocumentTerms($type, $id, Request $request) {
    // Check if type is allowed.
    $this->typeAllowed($type, 'read');

    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get allowed endpoints.
   */
  protected function typeAllowed($type, $mode = 'read') {
    // Allow read operations on "any" endpoint.
    if ($type === 'any' && $mode === 'read') {
      return 'any';
    }

    // Allow read operations on "all" endpoint.
    if ($type === 'all' && $mode === 'read') {
      return 'any';
    }

    return $this->EndpointGetNodeType($type);
  }

  /**
   * Check if provider can use field.
   */
  protected function providerCanUseDocumentField($field_name, $type, $provider) {
    static $cache = [];

    if (isset($cache[$type][$field_name][$provider->id])) {
      return $cache[$type][$field_name][$provider->id];
    }

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

    if (in_array($field_name, $public_fields)) {
      $cache[$type][$field_name][$provider->id] = TRUE;
      return TRUE;
    }

    $field_config = FieldConfig::loadByName('node', $type, $field_name);
    if (!$field_config) {
      throw new \Exception(strtr('Field @field does not exist', [
        '@field' => $field_name,
      ]));
    }

    if (!$provider->isAnonymous() && $field_config->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      $cache[$type][$field_name][$provider->id] = TRUE;
      return TRUE;
    }

    if (!$field_config->getThirdPartySetting('docstore', 'private', FALSE)) {
      $cache[$type][$field_name][$provider->id] = TRUE;
      return TRUE;
    }

    $cache[$type][$field_name][$provider->id] = FALSE;
    return FALSE;
  }

}
