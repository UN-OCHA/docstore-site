<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
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
   * @var Drupal\Core\State\State
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config,
      EntityFieldManagerInterface $entityFieldManager,
      EntityRepositoryInterface $entityRepository,
      EntityTypeManagerInterface $entityTypeManager,
      TransliterationInterface $transliteration,
      MimeTypeGuesser $mimeTypeGuesser,
      FileSystem $fileSystem,
      FileUsageInterface $fileUsage,
      LoggerChannelFactoryInterface $logger_factory,
      State $state
    ) {
    $this->config = $config;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityRepository = $entityRepository;
    $this->entityTypeManager = $entityTypeManager;
    $this->transliteration = $transliteration;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->fileSystem = $fileSystem;
    $this->fileUsage = $fileUsage;
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
  public function getDocuments() {
    $data = [];

    // Query index.
    $index = Index::load('documents');
    $query = $index->query();
    $results = $query->execute();

    // Use solr response directly.
    $solr_response = $results->getExtraData('search_api_solr_response', []);

    // Build output data.
    // TODO: Check if backend is solr.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();
    $data = $this->buildDocumentOutputFromSolr($solr_response['response']['docs'], $solr, $index);

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
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Build document output.
   */
  protected function buildDocumentOutputFromSolr($docs, $solr, $index) {
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

      // Re-write file information.
      $row['files'] = [];
      foreach ($row['files_media_uuid'] as $key => $value) {
        $row['files'][] = [
          'media_uuid' => $value,
          'file_uuid' => $row['files_file_uuid'][$key] ?? '',
          'file_filename' => $row['files_file_filename'][$key] ?? '',
          'files_file_uri' => $row['files_file_uri'][$key] ?? '',
          'files_file_filemime' => $row['files_file_filemime'][$key] ?? '',
        ];
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
            $values = [$values];
          }

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
    $data = $this->buildDocumentOutputFromSolr($solr_response['response']['docs'], $solr, $index);

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
    $data = [];

    $map = $this->entityFieldManager->getFieldDefinitions('node', 'document');
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'document_fields',
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    $response = new CacheableJsonResponse($data);

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

    // Check field parameters.
    $this->validFieldParameters($params, $provider);

    // Create field.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      $field_name = docstore_create_document_reference_field_for_provider($params['label'], $params['target'], $params['multiple'], $provider->get('prefix')->value);
    }
    else {
      $field_name = docstore_create_document_field_for_provider($params['label'], $params['type'], $params['multiple'], $provider->get('prefix')->value);
    }

    $data = [
      'message' => 'Field created',
      'field_name' => $field_name,
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Delete document field.
   */
  public function deleteDocumentField(Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get vocabularies.
   */
  public function getVocabularies() {
    $data = [];

    $vocabularies = $this->loadVocabularies();
    foreach ($vocabularies as $vocabulary) {
      $data[] = [
        'uuid' => $vocabulary->uuid(),
        'label' => $vocabulary->label(),
        'machine_name' => $vocabulary->id(),
      ];
    }

    $response = new CacheableJsonResponse($data);

    return $response;
  }

  /**
   * Create vocabulary.
   */
  public function createVocabulary(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Check required fields.
    if (empty($params['label'])) {
      throw new NotFoundHttpException();
    }

    // Get provider.
    $provider = $this->getProvider();

    // Create field.
    $machine_name = docstore_create_vocabulary_for_provider($params['label'], $provider->get('prefix')->value);

    // Add created field.
    docstore_create_vocabulary_base_field_created($machine_name);

    // Add provider uuid.
    docstore_create_vocabulary_base_field_provider_uuid($machine_name);

    // Add author HID.
    docstore_create_vocabulary_base_field_hid_id($machine_name);

    $data = [
      'message' => 'Vocabulary created',
      'machine_name' => $machine_name,
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
      'label' => $vocabulary->label(),
      'machine_name' => $vocabulary->id(),
    ];

    $response = new CacheableJsonResponse($data);

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
    $vocabulary = FALSE;
    if (Uuid::isValid($id)) {
      $vocabulary = $this->entityRepository->loadEntityByUuid('taxonomy_vocabulary', $id);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = Vocabulary::load($id);
    }

    if (!$vocabulary) {
      throw new NotFoundHttpException();
    }

    $data = [];
    $map = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $vocabulary->id());
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

    $response = new CacheableJsonResponse($data);

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
    $vocabulary = $this->getVocabularyMachineName($id, $provider->get('prefix')->value);

    // Check field parameters.
    $this->validFieldParameters($params, $provider);

    // Create field.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      $field_name = docstore_create_vocabulary_reference_field_for_provider($vocabulary->id(), $params['label'], $params['target'], $params['multiple'], $provider->get('prefix')->value);
    }
    else {
      $field_name = docstore_create_vocabulary_field_for_provider($vocabulary->id(), $params['label'], $params['type'], $params['multiple'], $provider->get('prefix')->value);
    }

    $data = [
      'message' => 'Field created',
      'field_name' => $field_name,
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Delete vocabulary fields.
   */
  public function deleteVocabularyField($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get provider.
   */
  protected function getProvider() {
    /** @var Drupal\Core\Session\AccountProxyInterface; */
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
    if (!docstore_vocabulary_is_valid($id, $provider_prefix)) {
      throw new BadRequestHttpException('Invalid vocabulary');
    }

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
   * Check field parameters.
   */
  protected function validFieldParameters(&$params, $provider) {
    // Multi value field.
    if (!isset($params['multiple'])) {
      $params['multiple'] = FALSE;
    }

    // Check required fields.
    if (empty($params['label'])) {
      throw new BadRequestHttpException('Label is required');
    }

    // If target is specified, type is not needed.
    if (isset($params['target'])) {
      $params['type'] = 'entity_reference_uuid';
    }
    else {
      if (empty($params['type'])) {
        throw new BadRequestHttpException('Type is required');
      }

      $allowed_types = docstore_allowed_field_types();
      if (!isset($allowed_types[$params['type']])) {
        throw new BadRequestHttpException('Unknown type');
      }
    }

    // Reference fields need a target as well.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      if (empty($params['target'])) {
        throw new BadRequestHttpException('Target is required for reference fields');
      }

      // Make sure bundle is valid.
      if (!docstore_vocabulary_is_valid($params['target'], $provider->get('prefix')->value)) {
        throw new BadRequestHttpException('Target does not exist or is invalid');
      }
    }
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
   * Create term.
   */
  public function createTerm(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Get provider.
    $provider = $this->getProvider();

    // Check required fields.
    if (empty($params['label'])) {
      throw new BadRequestHttpException('Label is required');
    }

    if (empty($params['vocabulary'])) {
      throw new BadRequestHttpException('Vocabulary is required');
    }

    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($params['vocabulary']);

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
    $item['created'][] = ['value' => time()];

    // Set owner.
    $item['base_provider_uuid'][] = ['target_uuid' => $provider->uuid()];

    $term = Term::create($item);
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
  public function getTerm($id, Request $request) {
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
   * Update term.
   */
  public function updateTerm($id, Request $request) {
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
    if (empty($params['label'])) {
      throw new BadRequestHttpException('Label is required');
    }

    if (isset($params['vocabulary'])) {
      throw new BadRequestHttpException('Vocabulary cannot be changed');
    }

    if (isset($params['vid'])) {
      throw new BadRequestHttpException('Vocabulary cannot be changed');
    }

    if (isset($params['created'])) {
      throw new BadRequestHttpException('Created cannot be changed');
    }

    if (isset($params['base_provider_uuid'])) {
      throw new BadRequestHttpException('Provider cannot be changed');
    }

    // Update all fields specified in params.
    foreach ($params as $name => $value) {
      if ($term->hasField($name)) {

      }
    }

    // Remove all fields not part of params.
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
    throw new PreconditionFailedHttpException('Not implemented (yet)');
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
        'uri' => $file->createFileUrl(),
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
      'uri' => $file->createFileUrl(),
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
      throw new BadRequestHttpException('Unable to write file');
    }

    return $vocabulary;
  }

  /**
   * Load all vocabularies.
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
      throw new BadRequestHttpException('Unable to write file');
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
        'changed' => $term->getChangedTime(),
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
            $values[] = $field_item->{$main_property_name};
          }
          else {
            $values[] = $field_item->value;
          }
        }
        if ($field_item_definition->getFieldStorageDefinition()->getCardinality() == 1) {
          $row[$term_field->getName()] = reset($values);
        }
        else {
          $row[$term_field->getName()] = $values;
        }
      }

      $data[] = $row;
    }

    return $data;
  }

}
