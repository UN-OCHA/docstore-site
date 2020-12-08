<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\user\Entity\User;
use Drupal\docstore\ParseQueryParameters;
use Drupal\docstore\ManageFields;
use Drupal\entity_usage\EntityUsage;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * Controller for API endpoints.
 */
class ApiController extends ControllerBase {

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * The mime type guesser service.
   *
   * @var \Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser
   */
  protected $mimeTypeGuesser;

  /**
   * The file system.
   *
   * @var Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The file usage.
   *
   * @var \Drupal\file\FileUsage\FileUsage
   */
  protected $fileUsage;

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
   * The state store.
   *
   * @var \Drupal\entity_usage\EntityUsage
   */
  protected $entityUsage;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config,
      Connection $database,
      EntityFieldManagerInterface $entityFieldManager,
      EntityRepositoryInterface $entityRepository,
      EntityTypeManagerInterface $entityTypeManager,
      TransliterationInterface $transliteration,
      MimeTypeGuesser $mimeTypeGuesser,
      FileSystem $fileSystem,
      FileUsageInterface $fileUsage,
      LoggerChannelFactoryInterface $logger_factory,
      State $state,
      EntityUsage $entityUsage
    ) {
    $this->config = $config;
    $this->database = $database;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityRepository = $entityRepository;
    $this->entityTypeManager = $entityTypeManager;
    $this->transliteration = $transliteration;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->fileSystem = $fileSystem;
    $this->fileUsage = $fileUsage;
    $this->loggerFactory = $logger_factory;
    $this->state = $state;
    $this->entityUsage = $entityUsage;
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

    $data = [];

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
    $data = $this->buildDocumentOutputFromSolr($solr_response['response']['docs'], $solr, $index, $request->getSchemeAndHttpHost());

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
        // @todo Only return base, shared and provider fields.
        if (isset($solr_row[$solr_name])) {
          $row[$name] = $solr_row[$solr_name];
        }
      }

      // Output tags as objects.
      foreach ($row as $key => $row_data) {
        if (isset($row[$key . '_label'])) {
          $tupples = [];
          foreach ($row_data as $tupple_key => $tupple_value) {
            $tupples[$tupple_key] = [
              'uuid' => $tupple_value,
              'name' => is_array($row[$key . '_label']) ? $row[$key . '_label'][$tupple_key] : $row[$key . '_label'],
            ];
          }
          $row[$key] = $tupples;
          unset($row[$key . '_label']);
        }
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

      $data[] = $row;
    }

    return $data;
  }

  /**
   * Create document.
   */
  public function createDocument($type, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'write');

    // Get provider.
    $provider = $this->requireProvider();

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Create document.
    $document = $this->createDocumentForProvider($type, $params, $provider);

    $data = [
      'message' => strtr('@type created', ['@type' => ucfirst($node_type)]),
      'uuid' => $document->uuid(),
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Create document for provider.
   */
  protected function createDocumentForProvider($type, $params, $provider) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'write');

    // Check required fields.
    if (empty($params['title'])) {
      throw new BadRequestHttpException('Title is required');
    }

    if (empty($params['author'])) {
      throw new BadRequestHttpException('Author is required');
    }

    // Create node.
    $item = [
      'type' => $node_type,
      'title' => $params['title'],
      'uid' => $provider->id(),
      'base_author_hid' => [],
      'base_files' => [],
      'base_private' => [],
      'status' => Node::PUBLISHED,
    ];

    // Published.
    if (isset($params['published']) && !$params['published']) {
      $item['status'] = Node::NOT_PUBLISHED;
    }

    // Private.
    if (isset($params['private']) && $params['private']) {
      $item['base_private'][] = [
        'value' => TRUE,
      ];
    }
    else {
      $item['base_private'][] = [
        'value' => FALSE,
      ];
    }

    // Store HID Id.
    $item['base_author_hid'][] = [
      'value' => $params['author'],
    ];

    // Attach files.
    if (isset($params['files']) && $params['files']) {
      $files = $params['files'];
      if (!is_array($files)) {
        $files = [$files];
      }

      // @todo allow file content and/or file name.
      foreach ($files as $uuid) {
        if (is_string($uuid)) {
          /** @var \Drupal\media\Entity\Media $media */
          $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
          if (!$media) {
            throw new BadRequestHttpException(strtr('Media @uuid does not exist', ['@uuid' => $uuid]));
          }

          $item['base_files'][] = [
            'target_uuid' => $media->uuid(),
          ];
        }
        else {
          if (isset($uuid['uri'])) {
            $media = $this->fetchAndCreateFile($uuid['uri'], $provider);
            $item['base_files'][] = [
              'target_uuid' => $media->uuid(),
            ];
          }
        }
      }
    }

    // Check for meta tags.
    if (isset($params['metadata']) && $params['metadata']) {
      $metadata = $params['metadata'];
      foreach ($metadata as $metaitem) {
        foreach ($metaitem as $key => $values) {
          // Check for label keys.
          if (strpos($key, '_label')) {
            $key = str_replace('_label', '', $key);
            $values = $this->mapOrCreateTerms($key, $values, $node_type, $provider, $params['author']);
          }

          if (!is_array($values)) {
            $item[$key][] = [
              'value' => $values,
            ];
          }
          else {
            if (!isset($item[$key])) {
              $item[$key] = [];
            }

            foreach ($values as $value) {
              $item[$key][] = [
                'target_uuid' => $value,
              ];
            }
          }
        }
      }
    }

    /** @var \Drupal\node\Entity\Node $document */
    $document = Node::create($item);

    // Trigger validation.
    $violations = $document->validate();
    if (count($violations) > 0) {
      throw new BadRequestHttpException(strtr('Unable to save document: @error (@path)', [
        '@error' => strip_tags($violations->get(0)->getMessage()),
        '@path' => $violations->get(0)->getPropertyPath(),
      ]));
    }

    // Save document.
    $document->save();

    // Invalidate cache.
    Cache::invalidateTags(['documents']);

    return $document;
  }

  /**
   * Fetch and create a file.
   */
  protected function fetchAndCreateFile($uri, $provider) {
    $content = file_get_contents($uri);

    // Create URI.
    $destination = $this->config('system.file')->get('default_scheme') . '://';

    $destination .= 'files/';
    $destination .= substr(md5(basename($uri)), 0, 3);
    $destination .= '/' . substr(md5(basename($uri)), 3, 3);
    $destination .= '/' . basename($uri);

    // Store files in sub directories.
    $file = File::create();
    $file->setOwnerId($provider->id());
    $file->setFileName(basename($uri));
    $file->setFileUri($destination);
    $file->setTemporary();

    $media = $this->saveFileToDisk($file, $content, $provider, FALSE);

    return $media;
  }

  /**
   * Create document.
   */
  public function createDocumentInBulk($type, Request $request) {
    // Get provider.
    $provider = $this->requireProvider();

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    if (empty($params['documents'])) {
      throw new BadRequestHttpException('documents is required');
    }

    $documents = $params['documents'];
    foreach ($documents as $document) {
      // Add common fields.
      $document['author'] = $params['author'];

      // Create document.
      $this->createDocumentForProvider($type, $document, $provider);
    }

    $data = [
      'message' => 'Processed',
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(200);

    return $response;
  }

  /**
   * Get document.
   */
  public function getDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'read');

    $data = [];

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

    $data = $this->buildDocumentOutputFromSolr($solr_response['response']['docs'], $solr, $index, $request->getSchemeAndHttpHost());

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
   * Update document.
   */
  public function updateDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'write');

    $protected_fields = [
      'base_author_hid',
      'base_provider_uuid',
      'changed',
      'created',
      'default_langcode',
      'langcode',
      'parent',
      'revision_created',
      'revision_id',
      'revision_log_message',
      'revision_user',
      'status',
      'promote',
      'sticky',
      'type',
      'nid',
      'uuid',
      'vid',
      'uid',
    ];

    // Load document.
    $document = $this->loadDocument($id);
    if (!$document) {
      throw new NotFoundHttpException(strtr('Document @uuid does not exist', ['@uuid' => $id]));
    }

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only update own document.
    if ($document->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('Document is not owned by you');
    }

    // Check required fields.
    if ($request->getMethod() === 'PUT') {
      if (empty($params['title'])) {
        throw new BadRequestHttpException('Title is required');
      }
    }

    // Re-map fields.
    if (isset($params['private'])) {
      $params['base_private'] = $params['private'];
      unset($params['private']);
    }

    if (isset($params['published'])) {
      $params['published'] = $params['published'];
      unset($params['published']);
    }

    $updated_fields = [];
    // Update all fields specified in metadata.
    if (isset($params['metadata'])) {
      $metadata = $params['metadata'];
      if (!is_array($metadata) || $this->arrayIsAssociative($metadata)) {
        throw new BadRequestHttpException('Metadata has to be an array');
      }

      foreach ($metadata as $metaitem) {
        foreach ($metaitem as $name => $values) {
          // Make sure protected fields aren't set.
          if (isset($protected_fields[$name])) {
            throw new BadRequestHttpException(strtr('Field @name cannot be changed', ['@name' => $name]));
          }

          if ($document->hasField($name)) {
            $document->set($name, $values);
            $updated_fields[] = $name;
          }
          else {
            throw new BadRequestHttpException(strtr('Field @name does not exists', ['@name' => $name]));
          }
        }
      }
      unset($params['metadata']);
    }

    // Update all fields specified in params.
    foreach ($params as $name => $values) {
      // Make sure protected fields aren't set.
      if (isset($protected_fields[$name])) {
        throw new BadRequestHttpException(strtr('Field @name cannot be changed', ['@name' => $name]));
      }

      if ($document->hasField($name)) {
        $document->set($name, $values);
        $updated_fields[] = $name;
      }
      else {
        throw new BadRequestHttpException(strtr('Field @name does not exists', ['@name' => $name]));
      }
    }

    // Remove all fields not part of params.
    if ($request->getMethod() === 'PUT') {
      $document_fields = $document->getFields(FALSE);
      foreach ($document_fields as $document_field) {
        // Skip name field.
        if ($document_field->getName() === 'title') {
          continue;
        }

        if (in_array($document_field->getName(), $updated_fields)) {
          continue;
        }

        if (in_array($document_field->getName(), $protected_fields)) {
          continue;
        }

        if (!$document_field->isEmpty()) {
          $document->set($document_field->getName(), NULL);
        }
      }
    }

    $document->setNewRevision();
    $document->revision_log = 'Updated';
    $document->setRevisionCreationTime(time());
    $document->isDefaultRevision(TRUE);
    $document->setRevisionUserId($provider->id());

    // Trigger validation.
    $violations = $document->validate();
    if (count($violations) > 0) {
      throw new BadRequestHttpException(strtr('Unable to save document: @error (@path)', [
        '@error' => strip_tags($violations->get(0)->getMessage()),
        '@path' => $violations->get(0)->getPropertyPath(),
      ]));
    }

    // Save document.
    $document->save();

    $data = [
      'message' => strtr('@type updated', ['@type' => ucfirst($node_type)]),
      'uuid' => $document->uuid(),
    ];

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $document->getCacheTags(),
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Delete document.
   */
  public function deleteDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'write');

    // Load document.
    $document = $this->loadDocument($id);

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only update own document.
    if ($document->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('Document is not owned by you');
    }

    $data = [
      'message' => strtr('@type deleted', ['@type' => ucfirst($node_type)]),
      'uuid' => $document->uuid(),
    ];

    $document->delete();

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get document fields.
   */
  public function getDocumentFields($type) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'field');

    // Load provider.
    $provider = $this->requireProvider();

    // Create field.
    $manager = new ManageFields($provider, $node_type, $this->entityFieldManager, $this->database);
    $data = $manager->getDocumentFields();

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'document_fields',
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Create document field.
   */
  public function createDocumentField($type, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'field');

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load provider.
    $provider = $this->requireProvider();

    // Create field.
    $manager = new ManageFields($provider, $node_type, $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $field_name = $manager->addDocumentField($params);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Field created',
      'field_name' => $field_name,
    ];

    // Invalidate cache.
    Cache::invalidateTags(['document_fields']);

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Get document field.
   */
  public function getDocumentField($type, $id, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'field');

    // Get provider.
    $provider = $this->requireProvider();

    // Get field config.
    $manager = new ManageFields($provider, $node_type, $this->entityFieldManager, $this->database);

    try {
      $data = $manager->getDocumentField($id);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'document_fields',
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Update document field.
   */
  public function updateDocumentField($type, $field, $id, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'field');

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load provider.
    $provider = $this->requireProvider();

    // Get manager.
    $manager = new ManageFields($provider, $node_type, $this->entityFieldManager, $this->database);

    // Update field.
    try {
      $field_name = $manager->updateDocumentField($id, $params);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Field updated',
      'field_name' => $field_name,
    ];

    // Invalidate cache.
    Cache::invalidateTags(['document_fields']);

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Delete document field.
   */
  public function deleteDocumentField($type, $id, Request $request) {
    // Check if type is allowed.
    $node_type = $this->typeAllowed($type, 'field');

    // Get provider.
    $provider = $this->requireProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, $node_type, $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $manager->deleteDocumentField($id);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Invalidate cache.
    Cache::invalidateTags(['document_fields']);

    $data = [
      'message' => 'Field deleted',
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get vocabularies.
   */
  public function getVocabularies() {
    $data = [];
    $cache_tags = [];

    $vocabularies = $this->loadVocabularies();
    foreach ($vocabularies as $vocabulary) {
      $data[] = [
        'uuid' => $vocabulary->uuid(),
        'machine_name' => $vocabulary->id(),
        'label' => $vocabulary->label(),
        'description' => $vocabulary->getDescription(),
      ];

      $cache_tags = array_merge($cache_tags, $vocabulary->getCacheTags());
    }

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $cache_tags,
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Create vocabulary.
   */
  public function createVocabulary(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $vocabulary = $manager->createVocabulary($params);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    $data = [
      'message' => 'Vocabulary created',
      'machine_name' => $vocabulary->id(),
      'uuid' => $vocabulary->uuid(),
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Get vocabulary.
   */
  public function getVocabulary($id) {
    $vocabulary = $this->loadVocabulary($id);

    $data = [
      'uuid' => $vocabulary->uuid(),
      'machine_name' => $vocabulary->id(),
      'label' => $vocabulary->label(),
      'description' => $vocabulary->getDescription(),
    ];

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $vocabulary->getCacheTags() + ['vocabularies'],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Update vocabulary.
   */
  public function updateVocabulary($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($id);

    // Get provider.
    $provider = $this->requireProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Update vocabulary.
    try {
      $vocabulary = $manager->updateVocabulary($vocabulary, $params, $request->getMethod());
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Vocabulary updated',
      'uuid' => $vocabulary->uuid(),
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Delete vocabulary.
   */
  public function deleteVocabulary($id, Request $request) {
    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($id);

    // Check if term is in use.
    if ($this->entityInUse($vocabulary)) {
      throw new BadRequestHttpException('Vocabulary is in use and can not be deleted');
    }

    // Get provider.
    $provider = $this->requireProvider();

    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Delete.
    try {
      $vocabulary = $manager->deleteVocabulary($vocabulary);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Vocabulary deleted',
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get vocabulary terms.
   */
  public function getVocabularyTerms($id, Request $request) {
    $vocabulary = $this->loadVocabulary($id);

    $data = $this->loadTerms([$vocabulary->id()]);

    $response = new CacheableJsonResponse($data);

    return $response;
  }

  /**
   * Get vocabulary fields.
   */
  public function getVocabularyFields($id) {
    $vocabulary = $this->loadVocabulary($id);

    $data = [];
    $map = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $vocabulary->id());
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

    $response = new CacheableJsonResponse($data);

    return $response;
  }

  /**
   * Get vocabulary field.
   */
  public function getVocabularyField($id, $field_id) {
    $vocabulary = $this->loadVocabulary($id);

    // Get provider.
    $provider = $this->requireProvider();

    // Get field config.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    try {
      $data = $manager->getVocabularyField($vocabulary, $field_id);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'vocabulary_fields',
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Create vocabulary fields.
   */
  public function createVocabularyField($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load provider.
    $provider = $this->requireProvider();

    // Id is either UUID or machine name.
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $field_name = $manager->addVocabularyField($vocabulary, $params);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Field created',
      'field_name' => $field_name,
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Update vocabulary fields.
   */
  public function updateVocabularyField($id, $field_id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load provider.
    $provider = $this->requireProvider();

    // Id is either UUID or machine name.
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $manager->updateVocabularyField($vocabulary, $field_id, $params);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Field updated',
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    $response = new JsonResponse($data);
    $response->setStatusCode(200);

    return $response;
  }

  /**
   * Delete vocabulary fields.
   */
  public function deleteVocabularyField($id, $field_id, Request $request) {
    // Load provider.
    $provider = $this->requireProvider();

    // Id is either UUID or machine name.
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $manager->deleteVocabularyField($vocabulary, $field_id);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Field deleted',
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    $response = new JsonResponse($data);
    $response->setStatusCode(200);

    return $response;
  }

  /**
   * Get provider.
   *
   * @return \Drupal\user_bundle\Entity\TypedUser|false
   *   The provider if found.
   */
  protected function getProvider() {
    /** @var Drupal\Core\Session\AccountProxy $current_user */
    $current_user = $this->currentUser();
    $provider = $current_user->getAccount();

    return $provider;
  }

  /**
   * Require provider.
   *
   * @return \Drupal\user_bundle\Entity\TypedUser|false
   *   The provider if found.
   */
  protected function requireProvider() {
    $provider = $this->getProvider();

    if (!$provider || $provider->isAnonymous()) {
      throw new BadRequestHttpException('Provider is required');
    }

    return $provider;
  }

  /**
   * Get vocabulary machine name.
   */
  protected function getVocabularyMachineName($id, $provider_prefix) {
    if (Uuid::isValid($id)) {
      $vocabulary = $this->entityRepository->loadEntityByUuid('taxonomy_vocabulary', $id);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($id);
      if (!$vocabulary) {
        throw new BadRequestHttpException('Invalid vocabulary');
      }
    }

    return $vocabulary;
  }

  /**
   * Get Terms.
   */
  public function getTerms(Request $request) {
    // Allow filtering by vocabularies.
    $vids = [];
    if ($request->get('vocabularies')) {
      if (is_array($request->get('vocabularies'))) {
        $vids = $request->get('vocabularies');
      }
      else {
        $vids = explode(',', $request->get('vocabularies'));
      }
    }
    else {
      // List of own and shared vocabularies.
      $vids = [];
    }

    $data = $this->loadTerms($vids);

    $response = new CacheableJsonResponse($data);

    return $response;
  }

  /**
   * Create term on vocabulary.
   */
  public function createTermOnVocabulary($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    $vocabulary = $this->loadVocabulary($id);

    $params['vocabulary'] = $vocabulary->uuid();
    return $this->createTermFromUserParameters($params);
  }

  /**
   * Create term.
   */
  public function createTerm(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    return $this->createTermFromUserParameters($params);
  }

  /**
   * Create term.
   */
  protected function createTermFromUserParameters($params) {
    // Get provider.
    $provider = $this->requireProvider();

    // Check required fields.
    if (empty($params['label'])) {
      throw new BadRequestHttpException('Label is required');
    }

    if (empty($params['vocabulary'])) {
      throw new BadRequestHttpException('Vocabulary is required');
    }

    if (empty($params['author'])) {
      throw new BadRequestHttpException('Author is required');
    }

    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($params['vocabulary']);

    if ($vocabulary->getThirdPartySetting('docstore', 'base_allow_duplicates') === FALSE) {
      // Check for duplicate labels.
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'name' => $params['label'],
        'vid' => $vocabulary->id(),
      ]);
      if ($terms) {
        throw new BadRequestHttpException('Term with same label already exists');
      }
    }

    $term = $this->createTermFromParameters($params, $vocabulary, $provider);

    $data = [
      'message' => 'Term created',
      'uuid' => $term->uuid(),
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Create a term.
   *
   * @param array $params
   *   Array of values.
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param \Drupal\user\Entity\User $provider
   *   Provider.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   Newly created term.
   */
  protected function createTermFromParameters(array $params, Vocabulary $vocabulary, User $provider) {
    // Term.
    $item = [
      'name' => $params['label'],
      'vid' => $vocabulary->id(),
      'created' => [],
      'base_provider_uuid' => [],
      'parent' => [],
      'description' => $params['description'] ?? '',
    ];

    // Set creation time.
    $item['created'][] = [
      'value' => time(),
    ];

    // Set owner.
    $item['base_provider_uuid'][] = [
      'target_uuid' => $provider->uuid(),
    ];

    // Store HID Id.
    $item['base_author_hid'][] = [
      'value' => $params['author'],
    ];

    // Check for meta tags.
    if (isset($params['metadata']) && $params['metadata']) {
      $metadata = $params['metadata'];
      if (!is_array($metadata) || $this->arrayIsAssociative($metadata)) {
        throw new BadRequestHttpException('Metadata has to be an array');
      }

      foreach ($metadata as $metaitem) {
        foreach ($metaitem as $key => $values) {
          if (!is_array($values)) {
            $item[$key][] = [
              'value' => $values,
            ];
          }
          else {
            if (!isset($item[$key])) {
              $item[$key] = [];
            }

            foreach ($values as $value) {
              $item[$key][] = [
                'target_uuid' => $value,
              ];
            }
          }
        }
      }
    }

    // Create term.
    $term = Term::create($item);

    // Check for invalid fields.
    foreach ($item as $key => $data) {
      if (!$term->hasField($key)) {
        throw new BadRequestHttpException(strtr('Unknown field @field', [
          '@field' => $key,
        ]));
      }
    }

    // Save.
    $term->save();

    return $term;
  }

  /**
   * Map or create terms based on field and label.
   */
  protected function mapOrCreateTerms($field_name, $values, $type, $provider, $author) {
    $field = FieldConfig::loadByName('node', $type, $field_name);

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

    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($bundle);

    // Loop values.
    if (!is_array($values)) {
      $values = [$values];
    }

    foreach ($values as &$value) {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'name' => $value,
        'vid' => $vocabulary->id(),
      ]);

      if ($terms) {
        $term = reset($terms);
        $value = $term->uuid();

        continue;
      }

      // Create term.
      $params = [
        'label' => $value,
      ];

      $term = $this->createTermFromParameters($params, $vocabulary, $provider);
      $value = $term->uuid();
    }

    return $values;
  }

  /**
   * Get term.
   */
  public function getTerm($id) {
    // Load term.
    $term = $this->loadTerm($id);
    $terms = $this->loadTerms([], $term->id());
    $data = reset($terms);

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $term->getCacheTags(),
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Get term revisions.
   */
  public function getTermRevisions($id, Request $request) {
    // Load term.
    $term = $this->loadTerm($id);
    $terms = $this->loadTerms([], $term->id());
    $data = reset($terms);

    $revisions = $this->database->select('taxonomy_term_revision', 'tr')
      ->fields('tr', [
        'revision_id',
        'revision_created',
        'revision_default',
        'revision_user',
        'revision_log_message',
      ])
      ->condition('tr.tid', $term->id())
      ->orderBy('revision_id', 'DESC')
      ->execute()
      ->fetchAll();

    $data['revisions'] = $revisions;

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $term->getCacheTags(),
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Update term.
   */
  public function updateTerm($id, Request $request) {
    $protected_fields = [
      'base_author_hid',
      'base_provider_uuid',
      'changed',
      'created',
      'default_langcode',
      'langcode',
      'parent',
      'revision_created',
      'revision_id',
      'revision_log_message',
      'revision_user',
      'status',
      'tid',
      'uuid',
      'vid',
      'vocabulary',
      'weight',
    ];

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load term.
    $term = $this->loadTerm($id);

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only update own terms.
    if ($term->base_provider_uuid->entity->uuid() !== $provider->uuid()) {
      throw new BadRequestHttpException('Term is not owned by you');
    }

    // Check required fields.
    if ($request->getMethod() === 'PUT') {
      if (empty($params['label'])) {
        throw new BadRequestHttpException('Label is required');
      }
    }

    // Label is actually name.
    if (isset($params['label'])) {
      $params['name'] = $params['label'];
      unset($params['label']);

      $vocabulary = $this->loadVocabulary($term->getVocabularyId());
      if ($vocabulary->getThirdPartySetting('docstore', 'base_allow_duplicates') === FALSE) {
        // Check for duplicate labels.
        $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
          'name' => $params['name'],
          'vid' => $vocabulary->id(),
        ]);

        if ($term->label() === $params['name'] && count($terms) > 1) {
          throw new BadRequestHttpException('Term with same label already exists');
        }

        if ($term->label() !== $params['name'] && count($terms) >= 1) {
          throw new BadRequestHttpException('Term with same label already exists');
        }
      }
    }

    $updated_fields = [];

    // Update all fields specified in metadata.
    if (isset($params['metadata'])) {
      $metadata = $params['metadata'];
      if (!is_array($metadata) || $this->arrayIsAssociative($metadata)) {
        throw new BadRequestHttpException('Metadata has to be an array');
      }

      foreach ($metadata as $metaitem) {
        foreach ($metaitem as $name => $values) {
          // Make sure protected fields aren't set.
          if (isset($protected_fields[$name])) {
            throw new BadRequestHttpException(strtr('Field @name cannot be changed', ['@name' => $name]));
          }

          if ($term->hasField($name)) {
            $term->set($name, $values);
            $updated_fields[] = $name;
          }
          else {
            throw new BadRequestHttpException(strtr('Field @name does not exists', ['@name' => $name]));
          }
        }
      }
      unset($params['metadata']);
    }

    // Update all fields specified in params.
    foreach ($params as $name => $values) {
      // Make sure protected fields aren't set.
      if (isset($protected_fields[$name])) {
        throw new BadRequestHttpException(strtr('Field @name cannot be changed', ['@name' => $name]));
      }

      if ($term->hasField($name)) {
        $term->set($name, $values);
        $updated_fields[] = $name;
      }
      else {
        throw new BadRequestHttpException(strtr('Field @name does not exists', ['@name' => $name]));
      }
    }

    // Remove all fields not part of params.
    if ($request->getMethod() === 'PUT') {
      $term_fields = $term->getFields(FALSE);
      foreach ($term_fields as $term_field) {
        // Skip name field.
        if ($term_field->getName() === 'name') {
          continue;
        }

        if (in_array($term_field->getName(), $updated_fields)) {
          continue;
        }

        if (in_array($term_field->getName(), $protected_fields)) {
          continue;
        }

        if (!$term_field->isEmpty()) {
          $term->set($term_field->getName(), NULL);
        }
      }
    }

    $term->setNewRevision();
    $term->revision_log = 'Term updated';
    $term->setRevisionCreationTime(time());
    $term->isDefaultRevision(TRUE);
    $term->setRevisionUserId($provider->id());

    $term->save();

    $data = [
      'message' => 'Term updated',
      'uuid' => $term->uuid(),
    ];

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $term->getCacheTags(),
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Delete term.
   */
  public function deleteTerm($id, Request $request) {
    // Load term.
    $term = $this->loadTerm($id);

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only update own terms.
    if ($term->base_provider_uuid->entity->uuid() !== $provider->uuid()) {
      throw new BadRequestHttpException('Term is not owned by you');
    }

    // Check if term is in use.
    if ($this->entityInUse($term)) {
      throw new BadRequestHttpException('Term is in use and can not be deleted');
    }

    $data = [
      'message' => 'Term deleted',
      'uuid' => $term->uuid(),
    ];

    $term->delete();

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get media.
   */
  public function getAllMedia(Request $request) {
    $data = [];

    $entities = $this->entityTypeManager->getStorage('media')->loadMultiple();

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($entities as $media) {
      /** @var \Drupal\media\Entity\File $file */
      $file = $this->entityTypeManager->getStorage('file')->load($media->getSource()->getSourceFieldValue($media));

      $data[] = [
        'uuid' => $media->uuid(),
        'name' => $media->getName(),
        'created' => $media->getCreatedTime(),
        'changed' => $media->getChangedTime(),
        'mimetype' => $file->getMimeType(),
        'file_uuid' => $file->uuid(),
        'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
      ];
    }

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get media.
   */
  public function getMedia($id, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $id);
    if (!$media) {
      throw new BadRequestHttpException('Media does not exist');
    }

    /** @var \Drupal\media\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($media->getSource()->getSourceFieldValue($media));

    // Get provider.
    $provider = $this->getProvider();

    // Provider can only get own private files.
    $private = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
    if ($private && $file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('Media is not owned by you');
    }

    $data = [
      'uuid' => $media->uuid(),
      'name' => $media->getName(),
      'created' => date(DATE_ATOM, $media->getCreatedTime()),
      'changed' => date(DATE_ATOM, $media->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'file_uuid' => $file->uuid(),
      'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
      'revisions' => [],
    ];

    $revisions = $this->entityTypeManager->getStorage('media')->getQuery()
      ->allRevisions()
      ->condition('mid', $media->id())
      ->sort('vid', 'DESC')
      ->pager(50)
      ->execute();

    $data['revisions'] = array_keys($revisions);

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get media revision.
   */
  public function getMediaRevision($id, $vid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $id);
    if (!$media) {
      throw new BadRequestHttpException('Media does not exist');
    }

    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->entityTypeManager->getStorage('media')->loadRevision($vid);
    if (!$revision) {
      throw new BadRequestHttpException('Revision does not exist');
    }

    if ($revision->id() !== $media->id()) {
      throw new BadRequestHttpException('Illegal revision detected');
    }

    /** @var \Drupal\media\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($revision->getSource()->getSourceFieldValue($revision));

    // Get provider.
    $provider = $this->getProvider();

    // Provider can only get own private files.
    $private = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
    if ($private && $file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    $data = [
      'uuid' => $revision->uuid(),
      'name' => $revision->getName(),
      'created' => date(DATE_ATOM, $revision->getCreatedTime()),
      'changed' => date(DATE_ATOM, $revision->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'file_uuid' => $file->uuid(),
      'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get media content.
   */
  public function getMediaContent($id, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $id);
    if (!$media) {
      throw new BadRequestHttpException('Media does not exist');
    }

    /** @var \Drupal\media\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($media->getSource()->getSourceFieldValue($media));

    // Get provider.
    $provider = $this->getProvider();

    // Provider can only get own private files.
    $private = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
    if ($private && $file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('Media is not owned by you');
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($media->getName()) . '"',
      'Cache-Control' => 'private',
    ];

    return new BinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Get media revision content.
   */
  public function getMediaRevisionContent($id, $vid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $id);
    if (!$media) {
      throw new BadRequestHttpException('Media does not exist');
    }

    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->entityTypeManager->getStorage('media')->loadRevision($vid);
    if (!$revision) {
      throw new BadRequestHttpException('Revision does not exist');
    }

    if ($revision->id() !== $media->id()) {
      throw new BadRequestHttpException('Illegal revision detected');
    }

    /** @var \Drupal\media\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($revision->getSource()->getSourceFieldValue($revision));

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only get own private files.
    $private = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
    if ($private && $file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($revision->getName()) . '"',
      'Cache-Control' => 'private',
    ];

    return new BinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Get files.
   */
  public function getFiles(Request $request) {
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple();
    $data = [];

    // Load provider.
    $provider = $this->getProvider();

    /** @var \Drupal\file\Entity\File $file */
    foreach ($files as $file) {
      $file_record = [
        'file' => $file->uuid(),
        'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
        'created' => date(DATE_ATOM, $file->getCreatedTime()),
        'changed' => date(DATE_ATOM, $file->getChangedTime()),
        'mimetype' => $file->getMimeType(),
      ];

      // Hide private files, unless it's the owner.
      if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
        $file_record['private'] = TRUE;
        if ($provider->isAnonymous() || $file->getOwnerId() !== $provider->uuid()) {
          unset($file_record['uri']);
        }
      }

      $data[] = $file_record;
    }

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Create file.
   */
  public function createFile(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load provider.
    $provider = $this->requireProvider();

    // Filename is required.
    if (!isset($params['filename'])) {
      throw new BadRequestHttpException('File name is required');
    }

    if (!isset($params['mimetype'])) {
      $params['mimetype'] = 'undefined';
    }

    if (!isset($params['alt'])) {
      $params['alt'] = $params['filename'];
    }

    // Support private files.
    $private = FALSE;
    if (isset($params['private']) && $params['private']) {
      $private = TRUE;
    }

    // Create URI.
    $destination = $this->config('system.file')->get('default_scheme') . '://';
    if ($private) {
      $destination = 'private://';
    }

    $destination .= 'files/';
    $destination .= substr(md5($params['filename']), 0, 3);
    $destination .= '/' . substr(md5($params['filename']), 3, 3);
    $destination .= '/' . $params['filename'];

    // Store files in sub directories.
    $file = File::create();
    $file->setOwnerId($provider->id());
    $file->setMimeType($params['mimetype']);
    $file->setFileName($params['filename']);
    $file->setFileUri($destination);
    $file->setTemporary();

    if (isset($params['data'])) {
      // Decode data.
      $content = base64_decode($params['data']);
      $this->saveFileToDisk($file, $content, $provider, FALSE);
    }
    elseif (isset($params['use_dropfolder']) && $params['use_dropfolder']) {
      if (!$provider->get('dropfolder')->value) {
        throw new BadRequestHttpException('Dropfolder is not enabled');
      }

      $files = $this->fileSystem->scanDirectory($provider->get('dropfolder')->value, '/^' . $params['filename'] . '$/');
      if ($files) {
        $files = array_keys($files);
        $first = reset($files);
        $content = file_get_contents($first);
        $this->saveFileToDisk($file, $content, $provider, FALSE);
      }
      else {
        throw new BadRequestHttpException('File not found in dropfolder');
      }
    }
    else {
      $file->save();
    }

    $data = [
      'message' => 'File created',
      'uuid' => $file->uuid(),
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Get file.
   */
  public function getFile($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    // Get provider.
    $provider = $this->getProvider();

    // Provider can only get own private files.
    $private = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
    if ($private && $file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    $data = [
      'file' => $file->uuid(),
      'filename' => $file->getFilename(),
      'url' => $file->createFileUrl(),
      'created' => date(DATE_ATOM, $file->getCreatedTime()),
      'changed' => date(DATE_ATOM, $file->getChangedTime()),
      'mimetype' => $file->getMimeType(),
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Update file.
   */
  public function updateFile($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only update own files.
    if ($file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    // Filename is required.
    if (isset($params['filename']) && $params['filename'] !== $file->getFilename()) {
      $file->setFilename($params['filename']);
      $file->save();
    }

    // See if we need to move the file.
    if (isset($params['private'])) {
      $private_state = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
      $private = FALSE;
      if ($params['private']) {
        $private = TRUE;
      }

      // See if private changed.
      if ($private_state !== $private) {
        // Move file.
        if ($private) {
          $this->moveFileToPrivate($file);
        }
        else {
          $this->moveFileToPublic($file);
        }
      }
    }

    $data = [
      'message' => 'File updated',
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Delete file.
   */
  public function deleteFile($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only delete own files.
    if ($file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    $usage_list = $this->fileUsage->listUsage($file);
    $usage_list = isset($usage_list['file']) ? $usage_list['file'] : [];
    if (count($usage_list) > 0) {
      throw new BadRequestHttpException(strtr('File is still in use in @num places', ['@num' => $usage_list]));
    }

    $file->delete();

    $data = [
      'message' => 'File is deleted',
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get file usage.
   */
  public function getFileUsage($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only get own private files.
    $private = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
    if ($private && $file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    $data = [];
    $usage_list = $this->fileUsage->listUsage($file);
    $usage_list = isset($usage_list['file']) ? $usage_list['file'] : [];
    foreach ($usage_list as $entity_type_id => $entity_ids) {
      $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple(array_keys($entity_ids));

      foreach ($entities as $entity) {
        $data[] = '/api/media/' . $entity->uuid();
      }
    }

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get file content.
   */
  public function getFileContent($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only get own private files.
    $private = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
    if ($private && $file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($file->getFilename()) . '"',
      'Cache-Control' => 'private',
    ];

    return new BinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Create file content.
   */
  public function createFileContent($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only update own files.
    if ($file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    $this->saveFileToDisk($file, $request->getContent(), $provider, TRUE);

    $data = [
      'message' => 'File content created',
      'uuid' => $file->uuid(),
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Update file content.
   */
  public function updateFileContent($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Load a vocabulary.
   *
   * @param string $id
   *   The vocabulary uuid or entity_id.
   *
   * @return \Drupal\taxonomy\Entity\Vocabulary
   *   Vocabulary.
   */
  protected function loadVocabulary($id) {
    if (Uuid::isValid($id)) {
      $vocabulary = $this->entityRepository->loadEntityByUuid('taxonomy_vocabulary', $id);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($id);
    }

    if (!$vocabulary) {
      throw new NotFoundHttpException('Vocabulary does not exist');
    }

    return $vocabulary;
  }

  /**
   * Load all vocabularies.
   *
   * @return \Drupal\taxonomy\Entity\Vocabulary[]
   *   Vocabularies.
   */
  protected function loadVocabularies() {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();

    return $vocabularies;
  }

  /**
   * Load a term.
   *
   * @param string $id
   *   The term uuid or entity_id.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   Term.
   */
  protected function loadTerm($id) {
    if (Uuid::isValid($id)) {
      $term = $this->entityRepository->loadEntityByUuid('taxonomy_term', $id);
    }
    else {
      // Assume it's the machine name.
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($id);
    }

    if (!$term) {
      throw new NotFoundHttpException('Term does not exist');
    }

    return $term;
  }

  /**
   * Load a document.
   *
   * @param string $id
   *   The document uuid.
   *
   * @return \Drupal\node\Entity\Node
   *   document.
   */
  protected function loadDocument($id) {
    if (Uuid::isValid($id)) {
      $document = $this->entityRepository->loadEntityByUuid('node', $id);
    }

    if (!$document) {
      throw new NotFoundHttpException('Document does not exist');
    }

    return $document;
  }

  /**
   * Save file content to disk.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   * @param string $content
   *   Full content of the file.
   * @param \Drupal\user\Entity\User $provider
   *   Provider.
   * @param bool $new_revision
   *   Create a new revision.
   */
  protected function saveFileToDisk(File &$file, $content, User $provider, $new_revision) {
    $is_new = TRUE;

    // Extract path from file.
    $destination = pathinfo($file->getFileUri(), PATHINFO_DIRNAME);
    $this->fileSystem->prepareDirectory($destination, $this->fileSystem::CREATE_DIRECTORY);

    // If file exists, copy existing and overwrite original.
    if ($new_revision && file_exists($file->getFileUri())) {
      $is_new = FALSE;

      // Create new file entity.
      /** @var \Drupal\file\Entity\File $new_file */
      $new_file = file_copy($file, $file->getFileUri(), $this->fileSystem::EXISTS_RENAME);
      if (!$new_file) {
        throw new BadRequestHttpException('Unable to write file' . $file->getFileUri());
      }

      // Detect mime type.
      if ($new_file->getMimeType() == 'undefined') {
        $new_file->setMimeType($this->mimeTypeGuesser->guess($new_file->getFileUri()));

        // Save file.
        $new_file->save();
      }

      // Swap filenames.
      $swap_uri = $file->getFileUri();
      $file->setFileUri($new_file->getFileUri());
      $new_file->setFileUri($swap_uri);

      // Save both.
      $file->save();
      $new_file->save();

      // Update media referencing this file.
      $usage_list = $this->fileUsage->listUsage($file);
      $usage_list = isset($usage_list['file']) ? $usage_list['file'] : [];
      $usage_list = isset($usage_list['media']) ? $usage_list['media'] : [];
      $usage_list = array_keys($usage_list);

      foreach ($usage_list as $media_id) {
        $media_entity = $this->entityTypeManager->getStorage('media')->load($media_id);

        // Move old file to revisions.
        $media_entity->field_media_file_revisions[] = [
          'target_id' => $file->id(),
        ];

        // Add new file.
        $media_entity->field_media_file = [
          'target_id' => $new_file->id(),
        ];

        // Force new revision and save.
        $media_entity->setNewRevision();
        $media_entity->save();
      }

      // Swap files so it gets the new content.
      $file = $new_file;
    }

    if ($uri = $this->fileSystem->saveData($content, $file->getFileUri(), $is_new ? $this->fileSystem::EXISTS_RENAME : $this->fileSystem::EXISTS_REPLACE)) {
      $file->setFileUri($uri);
      $file->setPermanent();

      // Detect mime type.
      if ($file->getMimeType() == 'undefined') {
        $file->setMimeType($this->mimeTypeGuesser->guess($uri));
      }

      // Save file.
      $file->save();

      // Create media if it's a new file.
      if ($is_new) {
        $media_entity = Media::create([
          'bundle' => 'file',
          'uid' => $provider->id(),
          'name' => $file->getFilename(),
          'status' => TRUE,
          'field_media_file' => [
            'target_id' => $file->id(),
          ],
        ]);
        $media_entity->save();
      }
    }
    else {
      throw new BadRequestHttpException('Unable to write file');
    }

    return $media_entity;
  }

  /**
   * Fetch terms.
   */
  public function loadTerms($vids = [], $tid = NULL) {
    $data = [];

    // Fields to hide.
    $hide_fields = [
      'tid',
      'revision_id',
      'vid',
      'revision_created',
      'revision_user',
      'revision_log_message',
      'weight',
      'changed',
      'parent',
      'changed',
    ];

    if ($tid) {
      $tids[] = $tid;
    }
    else {
      // Build query, use paging.
      $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();

      // Filter by vocabularies.
      if (!empty($vids)) {
        $query->condition('vid', $vids, 'IN');
      }

      $tids = $query->execute();
    }
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);

    /** @var \Drupal\Core\Entity\Term $term */
    foreach ($terms as $term) {
      $vocabulary = $this->loadVocabulary($term->getVocabularyId());
      $row = [
        'uuid' => $term->uuid(),
        'label' => $term->label(),
        'machine_name' => $term->id(),
        'vocabulary_name' => $term->getVocabularyId(),
        'vocabulary_uuid' => $vocabulary->uuid(),
        'changed' => date(DATE_ATOM, $term->getChangedTime()),
      ];

      // Add all fields.
      $term_fields = $term->getFields();
      foreach ($term_fields as $term_field) {
        if (in_array($term_field->getName(), $hide_fields)) {
          continue;
        }

        $field_item_list = isset($term->{$term_field->getName()}) ? $term->{$term_field->getName()} : NULL;
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
          $row[$term_field->getName()] = reset($values);
        }
        else {
          $row[$term_field->getName()] = $values;
        }
      }

      // Format timestamp.
      $row['created'] = date(DATE_ATOM, $row['created']);

      $data[] = $row;
    }

    return $data;
  }

  /**
   * Check if in entity is in use.
   *
   * \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if entity is used somewhere.
   */
  protected function entityInUse($entity) {
    return !empty($this->entityUsage->listSources($entity));
  }

  /**
   * Check if an array is associative.
   */
  protected function arrayIsAssociative(array $array) {
    return count(array_filter(array_keys($array), 'is_string')) > 0;
  }

  /**
   * Get allowed endpoints.
   */
  protected function typeAllowed($type, $mode = 'read') {
    $config = _docstore_get_allowed_api_endpoints();

    if (!isset($config[$type])) {
      throw new BadRequestHttpException('Type not supported.');
    }

    if (!isset($config[$type][$mode])) {
      throw new BadRequestHttpException('Type not supported.');
    }

    if (!$config[$type][$mode]) {
      throw new BadRequestHttpException('Type not supported.');
    }

    return $config[$type][$mode];
  }

  /**
   * Move file to private file system.
   */
  protected function moveFileToPrivate($file) {
    $uri = $file->getFileUri();
    $new_uri = $uri;

    if (strpos($uri, 'private://') === FALSE) {
      $new_uri = str_replace('public://', 'private://', $uri);
    }

    // Make sure we need to move.
    if ($uri === $new_uri) {
      return;
    }

    // Make sure directory exists.
    $destination = pathinfo($new_uri, PATHINFO_DIRNAME);
    $this->fileSystem->prepareDirectory($destination, $this->fileSystem::CREATE_DIRECTORY);

    // Move file and update record.
    if (!file_move($file, $new_uri)) {
      throw new BadRequestHttpException('File could not be moved');
    }
  }

  /**
   * Move file to public file system.
   */
  protected function moveFileToPublic($file) {
    $uri = $file->getFileUri();
    $new_uri = $uri;

    if (strpos($uri, 'public://') === FALSE) {
      $new_uri = str_replace('private://', 'public://', $uri);
    }

    // Make sure we need to move.
    if ($uri === $new_uri) {
      return;
    }

    // Make sure directory exists.
    $destination = pathinfo($new_uri, PATHINFO_DIRNAME);
    $this->fileSystem->prepareDirectory($destination, $this->fileSystem::CREATE_DIRECTORY);

    // Move file and update record.
    if (!file_move($file, $new_uri)) {
      throw new BadRequestHttpException('File could not be moved');
    }
  }

}
