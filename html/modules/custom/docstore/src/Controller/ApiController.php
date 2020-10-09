<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
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
  public function __construct(ConfigFactoryInterface $config, LoggerChannelFactoryInterface $logger_factory, State $state) {
    $this->config = $config->get('docstore.settings');
    $this->loggerFactory = $logger_factory;
    $this->state = $state;
  }

  /**
   * Get documents.
   */
  public function getDocuments() {
    $data = [];

    // Query index.
    $index = \Drupal\search_api\Entity\Index::load('documents');
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
        // TODO: Track actual node ids + search api index.
        'node',
      ],
    ];

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
      foreach($field_mapping as $name => $solr_name) {
        // TODO: Only return base, shared and provider fields.
        if (isset($solr_row[$solr_name])) {
          // TODO: Check cardinality.
          $row[$name] = $solr_row[$solr_name];
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
      if (isset($row['search_api_id'])) {
        unset($row['search_api_id']);
      }
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
        $media = \Drupal::service('entity.repository')->loadEntityByUuid('media', $uuid);
        if (!$media) {
          throw new BadRequestHttpException($this->t('Media @uuid does not exist', ['@uuid' => $uuid]));
        }

        $item['base_files'][] = [
          'target_uuid' => $media->uuid(),
        ];
      }
    }

    // Check for meta tags.
    if (isset($params['metadata']) && $params['metadata']) {
      $metadata = $params['metadata'];
      foreach($metadata as $metaitem) {
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

    $data = [
      'message' => 'Document created',
      'uuid' => $document->uuid(),
    ];
    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get document.
   */
  public function getDocument($id, Request $request) {
    $data = [];

    // Query index.
    $index = \Drupal\search_api\Entity\Index::load('documents');
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
      throw new BadRequestHttpException($this->t('Document @uuid does not exist', ['@uuid' => $id]));
    }

    $data = reset($data);

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        // TODO: Track actual node ids + search api index.
        'node',
      ],
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
    $data = [];

    $map = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'document');
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

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

    $vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
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

    $data = [
      'message' => 'Vocabulary created',
      'machine_name' => $machine_name,
    ];
    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get vocabulary.
   */
  public function getVocabulary($id) {
    $vocabulary = FALSE;
    if (Uuid::isValid($id)) {
      $vocabulary = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_vocabulary', $id);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::load($id);
    }

    if (!$vocabulary) {
      throw new NotFoundHttpException();
    }

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
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get vocabulary fields.
   */
  public function getVocabularyFields($id) {
    $vocabulary = FALSE;
    if (Uuid::isValid($id)) {
      $vocabulary = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_vocabulary', $id);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::load($id);
    }

    if (!$vocabulary) {
      throw new NotFoundHttpException();
    }

    $data = [];
    $map = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', $vocabulary->id());
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

    return $response;
  }

  /**
   * Create vocabulary fields.
   */
  public function deleteVocabularyField($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get provider.
   */
  protected function getProvider() {
    // Get proxy account to get session info.
    $provider = \Drupal::currentUser()->getAccount();

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
      $vocabulary = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_vocabulary', $id);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::load($id);
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
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Create term.
   */
  public function createTerm(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Check required fields.
    if (empty($params['label'])) {
      throw new BadRequestHttpException('Label is required');
    }

    if (empty($params['vocabulary'])) {
      throw new BadRequestHttpException('Vocabulary is required');
    }

    // TODO refactor in separate function.
    $vocabulary = FALSE;
    if (Uuid::isValid($params['vocabulary'])) {
      $vocabulary = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_vocabulary', $params['vocabulary']);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::load($params['vocabulary']);
    }

    if (!$vocabulary) {
      throw new BadRequestHttpException('Vocabulary does not exist');
    }

    // Term.
    $term = Term::create([
      'name' => $params['label'],
      'vid' => $vocabulary->id(),
      'parent' => [],
    ]);
    $term->save();

    $data = [
      'message' => 'Term created',
      'machine_name' => $term->uuid(),
    ];
    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get term.
   */
  public function getTerm($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Update term.
   */
  public function updateTerm($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
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
  public function getMedia($id, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = \Drupal::service('entity.repository')->loadEntityByUuid('media', $id);
    if (!$media) {
      throw new BadRequestHttpException('Media does not exist');
    }

    $file = File::load($media->getSource()->getSourceFieldValue($media));

    $data = [
      'uuid' => $media->uuid(),
      'name' => $media->getName(),
      'created' => $media->getCreatedTime(),
      'updated' => $media->getChangedTime(),
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

    //$filesystem = \Drupal::service('file_system');
    $file = File::create();
    $file->setOwnerId($provider->id());
    $file->setMimeType($params['mimetype']);
    $file->setFileName($params['filename']);
    $file->setFileUri($params['filename']);
    $file->setTemporary();

    if (isset($params['data'])) {
      // Create destination.
      $destination = file_default_scheme() . '://';
      $destination .= substr(md5($params['filename']), 0, 3);
      $destination .= '/' . substr(md5($params['filename']), 3, 3);
      file_prepare_directory($destination, FILE_CREATE_DIRECTORY);

      // Append filename.
      $trans = \Drupal::transliteration();
      $params['filename'] = $trans->transliterate($params['filename'], 'en');
      $params['filename'] = preg_replace('/\-+/', '-', strtolower(preg_replace('/[^a-zA-Z0-9_\-\.]+/', '', str_replace(' ', '-', $params['filename']))));
      $destination .= '/' . $params['filename'];

      // Decode data.
      $params['data'] = base64_decode($params['data']);

      if ($uri = file_unmanaged_save_data($params['data'], $destination, FILE_EXISTS_RENAME)) {
        $file->setFileUri($uri);
        $file->setPermanent();

        // Detect mime type.
        if ($file->getMimeType() == 'undefined') {
          $file->setMimeType(\Drupal::service('file.mime_type.guesser')->guess($uri));
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
            'alt' => $params['alt'],
          ],
        ]);
        $media_entity->save();
      }
      else {
        throw new BadRequestHttpException('Unable to write file');
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

    return $response;
  }

  /**
   * Get file.
   */
  public function getFile($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    $data = [
      'file' => $file->uuid(),
      'url' => $file->createFileUrl(),
      'created' => $file->getCreatedTime(),
      'updated' => $file->getChangedTime(),
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
    $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    $usage_list = \Drupal::service('file.usage')->listUsage($file);
    $usage_list = isset($usage_list['file']) ? $usage_list['file'] : array();
    if (count($usage_list) > 0) {
      throw new BadRequestHttpException($this->t('File is still in use in @num places', ['@num' => $usage_list]));
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
    $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    $data = [];
    $usage_list = \Drupal::service('file.usage')->listUsage($file);
    $usage_list = isset($usage_list['file']) ? $usage_list['file'] : array();
    foreach ($usage_list as $entity_type_id => $entity_ids) {
      $entities = \Drupal::entityTypeManager()
        ->getStorage($entity_type_id)
        ->loadMultiple(array_keys($entity_ids));

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
    $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    // Get provider.
    $provider = $this->getProvider();

    // Provider can only update own files.
    if ($file->getOwnerId() !== $provider->id()) {
      throw new BadRequestHttpException('File is not owned by you');
    }

    // TODO Throw error if file already exists on disk.

    // Create destination.
    $destination = file_default_scheme() . '://';
    $destination .= substr(md5($file->getFilename()), 0, 3);
    $destination .= '/' . substr(md5($file->getFilename()), 3, 3);
    file_prepare_directory($destination, FILE_CREATE_DIRECTORY);

    // Append filename.
    $destination .= '/' . $file->getFilename();

    if ($uri = file_unmanaged_save_data($request->getContent(), $destination, FILE_EXISTS_RENAME)) {
      $file->setFileUri($uri);
      $file->setPermanent();

      // Detect mime type.
      if ($file->getMimeType() == 'undefined') {
        $file->setMimeType(\Drupal::service('file.mime_type.guesser')->guess($uri));
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
          'alt' => $params['alt'],
        ],
      ]);
      $media_entity->save();
    }
    else {
      throw new BadRequestHttpException('Unable to write file');
    }

    $data = [
      'message' => 'File content created',
      'uuid' => $file->uuid(),
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Update file content.
   */
  public function updateFileContent($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

}
