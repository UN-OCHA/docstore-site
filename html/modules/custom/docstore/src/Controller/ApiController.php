<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    $map = \Drupal::service('entity_field.manager')->getFieldMap();
    foreach ($map['node'] as $field_name => $field_info) {
      $data[$field_name] = $field_info['type'];
    }

    $response = new CacheableJsonResponse($data);

    return $response;
  }

  /**
   * Get document fields.
   */
  public function addDocumentField(Request $request) {
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
