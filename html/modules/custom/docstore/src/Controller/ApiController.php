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

    // Get field mappings.
    $server = $index->getServerInstance();
    $solr = $server->getBackend();

    // TODO: Check if backend is solr.
    $field_mapping = $solr->getSolrFieldNames($index);
    $language_field = $field_mapping['search_api_language'];

    foreach ($solr_response['response']['docs'] as $solr_row) {
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

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        // TODO: Track actual node ids + search api.
        'node',
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Create document.
   */
  public function createDocument(Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Get document.
   */
  public function getDocument($id, Request $request) {
    throw new PreconditionFailedHttpException('Not implemented (yet)');
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
      $field_name = docstore_create_document_reference_field_for_provider($params['label'], $params['target'], $params['multiple'], $provider->get('base_prefix')->value);
    }
    else {
      $field_name = docstore_create_document_field_for_provider($params['label'], $params['type'], $params['multiple'], $provider->get('base_prefix')->value);
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

    // Get proxy account to get session info.
    $user = \Drupal::currentUser()->getAccount();

    // Load provider.
    $provider = taxonomy_term_load($user->docstore_provider);

    // Create field.
    $machine_name = docstore_create_vocabulary_for_provider($params['label'], $provider->get('base_prefix')->value);

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
    $vocabulary = $this->getVocabularyMachineName($id, $provider->get('base_prefix')->value);

    // Check field parameters.
    $this->validFieldParameters($params, $provider);

    // Create field.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      $field_name = docstore_create_vocabulary_reference_field_for_provider($vocabulary->id(), $params['label'], $params['target'], $params['multiple'], $provider->get('base_prefix')->value);
    }
    else {
      $field_name = docstore_create_vocabulary_field_for_provider($vocabulary->id(), $params['label'], $params['type'], $params['multiple'], $provider->get('base_prefix')->value);
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
    $user = \Drupal::currentUser()->getAccount();

    // Load provider.
    $provider = taxonomy_term_load($user->docstore_provider);

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
      if (!docstore_vocabulary_is_valid($params['target'], $provider->get('base_prefix')->value)) {
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
    throw new PreconditionFailedHttpException('Not implemented (yet)');
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
      'file' => $file->uuid(),
      'url' => $file->createFileUrl(),
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

    // Load provider?
    $provider = $this->getProvider();

    // Filename is required.
    if (!isset($params['filename'])) {
      throw new BadRequestHttpException('File name is required');
    }

    if (!isset($params['mime'])) {
      $params['mime'] = 'undefined';
    }

    if (!isset($params['alt'])) {
      $params['alt'] = $params['filename'];
    }

    // TODO: Support public vs private.

    //$filesystem = \Drupal::service('file_system');
    $file = File::create();
    $file->setOwnerId($provider->id());
    $file->setMimeType($params['mime']);
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
          // TODO
        }

        // Save file.
        $file->save();

        // Create media.
        $media_entity = Media::create([
          'bundle' => 'file',
          'uid' => '0',
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
    /** @var Drupal\file\Entity\File $file */
    $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }
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
    throw new PreconditionFailedHttpException('Not implemented (yet)');
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
    /** @var Drupal\file\Entity\File $file */
    $file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
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
        // TODO
      }

      // Save file.
      $file->save();

      // Create media.
      $media_entity = Media::create([
        'bundle' => 'file',
        'uid' => '0',
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
