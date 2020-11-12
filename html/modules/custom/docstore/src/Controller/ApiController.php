<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Transliteration\TransliterationInterface;
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
use Drupal\docstore\ParseQueryParameters;
use Drupal\docstore\ManageFields;
use Drupal\entity_usage\EntityUsage;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * Class ApiController.
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
  public function getDocuments(Request $request) {
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
    if ($request->query->has('page')) {
      $pagers = $parser->parsePaging($request->query->get('page'));
      $parser->applyPagerToIndex($pagers, $query);
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
    // TODO: Check if backend is solr.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();

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

    $field_mapping = $solr->getSolrFieldNames($index);
    $language_field = $field_mapping['search_api_language'];

    foreach ($docs as $solr_row) {
      if ($language_field && isset($solr_row[$language_field])) {
        $language_id = $solr_row[$language_field];
        $field_mapping = $solr->getLanguageSpecificSolrFieldNames($language_id, $index);
      }

      $row = [];
      foreach ($field_mapping as $name => $solr_name) {
        // TODO: Only return base, shared and provider fields.
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

      // Rename base fields.
      $row['provider'] = $row['base_provider_uuid'];
      unset($row['base_provider_uuid']);

      $row['author'] = $row['base_author_hid'];
      unset($row['base_author_hid']);

      // Re-write file information.
      $row['files'] = [];
      if (isset($row['files_media_uuid'])) {
        foreach ($row['files_media_uuid'] as $key => $value) {
          $row['files'][] = [
            'media_uuid' => $value,
            'file_uuid' => $row['files_file_uuid'][$key] ?? '',
            'filename' => $row['files_file_filename'][$key] ?? '',
            'uri' => $base_url . $row['files_file_uri'][$key] ?? '',
            'mime' => $row['files_file_filemime'][$key] ?? '',
          ];
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

      $data[] = $row;
    }

    return $data;
  }

  /**
   * Create document.
   */
  public function createDocument(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Check required fields.
    if (empty($params['title'])) {
      throw new BadRequestHttpException('Title is required');
    }

    if (empty($params['author'])) {
      throw new BadRequestHttpException('Author is required');
    }

    // Get provider.
    $provider = $this->getProvider();

    // Create node.
    $item = [
      'type' => 'document',
      'title' => $params['title'],
      'uid' => $provider->id(),
      'base_author_hid' => [],
      'base_files' => [],
    ];

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

      foreach ($files as $uuid) {
        /** @var \Drupal\media\Entity\Media $media */
        $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
        if (!$media) {
          throw new BadRequestHttpException(strtr('Media @uuid does not exist', ['@uuid' => $uuid]));
        }

        $item['base_files'][] = [
          'target_uuid' => $media->uuid(),
        ];
      }
    }

    // Check for meta tags.
    if (isset($params['metadata']) && $params['metadata']) {
      $metadata = $params['metadata'];
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

    /** @var \Drupal\node\Entity\Node $document */
    $document = Node::create($item);
    $document->save();

    // Invalidate cache.
    Cache::invalidateTags(['documents']);

    $data = [
      'message' => 'Document created',
      'uuid' => $document->uuid(),
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Get document.
   */
  public function getDocument($id, Request $request) {
    $data = [];

    // Query index.
    $index = Index::load('documents');
    $query = $index->query();
    $query->addCondition('uuid', $id);
    $results = $query->execute();

    // Use solr response directly.
    $solr_response = $results->getExtraData('search_api_solr_response', []);

    // Build output data.
    // TODO: Check if backend is solr.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();
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
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Get document revisions.
   */
  public function getDocumentRevisions($id, Request $request) {
    $data = [];

    // Query index.
    $index = Index::load('documents');
    $query = $index->query();
    $query->addCondition('uuid', $id);
    $results = $query->execute();

    // Use solr response directly.
    $solr_response = $results->getExtraData('search_api_solr_response', []);

    // Build output data.
    // TODO: Check if backend is solr.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();
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
  public function getDocumentRevision($id, $vid, Request $request) {
    /** @var \Drupal\node\Entity\Node $document */
    $document = $this->entityTypeManager->getStorage('node')->loadRevision($vid);
    if ($document->uuid() !== $id) {
      throw new BadRequestHttpException('Revision not found');
    }

    $data = [];
    $document_fields = $document->getFields(FALSE);
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
  public function getDocumentFiles($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get document terms.
   */
  public function getDocumentTerms($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Update document.
   */
  public function updateDocument($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Delete document.
   */
  public function deleteDocument($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get document fields.
   */
  public function getDocumentFields() {
    // Load provider.
    $provider = $this->getProvider();

    // Create field.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);
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
  public function createDocumentField(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Load provider.
    $provider = $this->getProvider();

    // Create field.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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
  public function getDocumentField($id, Request $request) {
    // Get provider.
    $provider = $this->getProvider();

    // Get field config.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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
  public function updateDocumentField($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Load provider.
    $provider = $this->getProvider();

    // Get manager.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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
  public function deleteDocumentField($id, Request $request) {
    // Get provider.
    $provider = $this->getProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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

      $cache_tags[] = $vocabulary->getCacheTags();
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

    // Get provider.
    $provider = $this->getProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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
    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($id);

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Get provider.
    $provider = $this->getProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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
    $provider = $this->getProvider();

    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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
    $provider = $this->getProvider();

    // Get field config.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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

    // Load provider.
    $provider = $this->getProvider();

    // Id is either UUID or machine name.
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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

    // Load provider.
    $provider = $this->getProvider();

    // Id is either UUID or machine name.
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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
    $provider = $this->getProvider();

    // Id is either UUID or machine name.
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, $this->entityFieldManager, $this->database);

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
   */
  protected function getProvider() {
    /** @var Drupal\Core\Session\AccountProxy $current_user */
    $current_user = $this->currentUser();
    $provider = $current_user->getAccount();

    if (!$provider) {
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
      $vocabulary = Vocabulary::load($id);
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
    $vocabulary = $this->loadVocabulary($id);

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    $params['vocabulary'] = $vocabulary->uuid();
    return $this->createTermFromParameters($params);
  }

  /**
   * Create term.
   */
  public function createTerm(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    return $this->createTermFromParameters($params);
  }

  /**
   * Create term.
   */
  protected function createTermFromParameters($params) {
    // Get provider.
    $provider = $this->getProvider();

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

    $term = Term::create($item);

    foreach ($item as $key => $data) {
      if (!$term->hasField($key)) {
        throw new BadRequestHttpException('Unknown field');
      }
    }

    $term->save();

    $data = [
      'message' => 'Term created',
      'uuid' => $term->uuid(),
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
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

    // Load term.
    $term = $this->loadTerm($id);

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Get provider.
    $provider = $this->getProvider();

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
    $provider = $this->getProvider();

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

    $data = [
      'uuid' => $media->uuid(),
      'name' => $media->getName(),
      'created' => $media->getCreatedTime(),
      'changed' => $media->getChangedTime(),
      'mimetype' => $file->getMimeType(),
      'file_uuid' => $file->uuid(),
      'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get files.
   */
  public function getFiles(Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Create file.
   */
  public function createFile(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Load provider.
    $provider = $this->getProvider();

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

    // TODO: Support public vs private.
    $file = File::create();
    $file->setOwnerId($provider->id());
    $file->setMimeType($params['mimetype']);
    $file->setFileName($params['filename']);
    $file->setFileUri($params['filename']);
    $file->setTemporary();

    if (isset($params['data'])) {
      // Decode data.
      $content = base64_decode($params['data']);
      $this->saveFileToDisk($file, $content, $provider);
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

    $data = [
      'file' => $file->uuid(),
      'url' => $file->createFileUrl(),
      'created' => $file->getCreatedTime(),
      'changed' => $file->getChangedTime(),
      'mimetype' => $file->getMimeType(),
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Update file.
   */
  public function updateFile($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
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
    throw new PreconditionFailedHttpException('Not implemented (yet)');
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
    $provider = $this->getProvider();

    // Provider can only update own files.
    if ($file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    $this->saveFileToDisk($file, $request->getContent(), $provider);

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
   * Save file content to disk.
   */
  protected function saveFileToDisk(&$file, $content, $provider) {
    // Create destination.
    $destination = $this->config('system.file')->get('default_scheme') . '://';
    $destination .= substr(md5($file->getFilename()), 0, 3);
    $destination .= '/' . substr(md5($file->getFilename()), 3, 3);
    $this->fileSystem->prepareDirectory($destination, $this->fileSystem::CREATE_DIRECTORY);

    // Append filename.
    $destination .= '/' . $file->getFilename();

    if ($uri = $this->fileSystem->saveData($content, $destination, $this->fileSystem::EXISTS_RENAME)) {
      $file->setFileUri($uri);
      $file->setPermanent();

      // Detect mime type.
      if ($file->getMimeType() == 'undefined') {
        $file->setMimeType($this->mimeTypeGuesser->guess($uri));
      }

      // Save file.
      $file->save();

      // Create media.
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
    else {
      throw new BadRequestHttpException('Unable to write file');
    }
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

}
