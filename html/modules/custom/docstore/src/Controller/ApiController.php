<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
   * Get document fields.
   */
  public function addDocumentField(Request $request) {
    // Get proxy account to get session info.
    $user = \Drupal::currentUser()->getAccount();

    // Load provider.
    $provider = taxonomy_term_load($user->docstore_provider);
    if (!$provider) {
      throw new BadRequestHttpException('Provider is required');
    }

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

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

    // Multi value field.
    $multiple = FALSE;
    if (isset($params['multiple'])) {
      $multiple = $params['multiple'];
    }

    // Create field.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      $field_name = docstore_create_document_reference_field_for_provider($params['label'], $params['target'], $multiple, $provider->get('base_prefix')->value);
    }
    else {
      $field_name = docstore_create_document_field_for_provider($params['label'], $params['type'], $multiple, $provider->get('base_prefix')->value);
    }

    $data = [
      'message' => 'Field added',
      'field_name' => $field_name,
    ];
    $response = new JsonResponse($data);

    return $response;
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
   * Add vocabulary.
   */
  public function addVocabulary(Request $request) {
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
      'message' => 'Vocabulary added',
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
   * Get vocabulary fields.
   */
  public function addVocabularyField($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Check required fields.
    if (empty($params['label']) || empty($params['type'])) {
      throw new NotFoundHttpException();
    }

    // Multi value field.
    $multiple = FALSE;
    if (isset($params['multiple'])) {
      $multiple = $params['multiple'];
    }

    // Get proxy account to get session info.
    $user = \Drupal::currentUser()->getAccount();

    // Load provider.
    $provider = taxonomy_term_load($user->docstore_provider);

    // Create field.
    $field_name = docstore_create_document_field_for_provider($params['label'], $params['type'], $multiple, $provider->get('base_prefix')->value);

    $data = [
      'message' => 'Field added',
      'field_name' => $field_name,
    ];
    $response = new JsonResponse($data);

    return $response;
  }
}
