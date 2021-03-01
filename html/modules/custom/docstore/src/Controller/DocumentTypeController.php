<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\docstore\DocumentTypeTrait;
use Drupal\docstore\ManageFields;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for document type endpoints.
 */
class DocumentTypeController extends ControllerBase {

  use DocumentTypeTrait;
  use ProviderTrait;
  use ResourceTrait;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory,
      Connection $database,
      EntityFieldManagerInterface $entityFieldManager,
      EntityRepositoryInterface $entityRepository,
      EntityTypeManagerInterface $entityTypeManager,
      LoggerChannelFactoryInterface $logger_factory
    ) {
    $this->configFactory = $configFactory;
    $this->database = $database;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityRepository = $entityRepository;
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get document types.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of vocabularies.
   */
  public function getDocumentTypes(Request $request) {
    $data = [];
    $entity_type_id = 'node_type';

    // Set the response cache.
    $cache = $this->createResponseCache()->addCacheTags(['document_types']);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // Get the list of documen types accessible to the provider.
    $node_type_ids = $this->getAccessibleResourceTypes($entity_type_id, $provider);

    // Get the node type storage and the id field.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $id_key = $storage->getEntityType()->getKey('id');

    /** @var \Drupal\node\Entity\NodeType[] $node_types */
    $node_types = $storage->loadByProperties([$id_key => $node_type_ids]);

    // Prepare the vocabularies data.
    foreach ($node_types as $node_type) {
      $data[] = $this->buildDocumentTypeJsonOutput($node_type);
      $cache->addCacheableDependency($node_type);
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get document type.
   *
   * @param string $type
   *   Document type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of vocabularies.
   */
  public function getDocumentType($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // Set the response cache.
    $cache = $this->createResponseCache()
      ->addCacheTags(['document_types'])
      ->addCacheableDependency($node_type);

    // Check if the vocabulary is accessible to the provider.
    try {
      $this->getAccessibleResourceTypes('node_type', $provider, $node_type->id());
    }
    catch (AccessDeniedHttpException $exception) {
      throw new CacheableAccessDeniedHttpException($cache, 'You are not allowed to access the document type');
    }

    $data = $this->buildDocumentTypeJsonOutput($node_type);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get the document type or document types accessible to the provider.
   *
   * @param string $id
   *   Node type ID. If not defined, return all the document types accessible
   *   to the provider.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of document types or document type's data.
   */
  public function getAccessibleDocumentTypes($id = NULL) {
    $data = [];
    $entity_type_id = 'node_type';

    // Set the response cache.
    $cache = $this->createResponseCache()->addCacheTags(['document_types']);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // Get the list of documen types accessible to the provider.
    // If `id` is set, then the list will just contain it.
    $node_type_ids = $this->getAccessibleResourceTypes($entity_type_id, $provider, $id);

    // Get the node type storage and the id field.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $id_key = $storage->getEntityType()->getKey('id');

    /** @var \Drupal\node\Entity\NodeType[] $node_types */
    $node_types = $storage->loadByProperties([$id_key => $node_type_ids]);

    // Prepare the vocabularies data.
    foreach ($node_types as $node_type) {
      $data[] = $this->buildDocumentTypeJsonOutput($node_type);
      $cache->addCacheableDependency($node_type);
    }

    // Only return the data for the given document type id.
    if (isset($id)) {
      $data = reset($data);
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create document type.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   API response.
   */
  public function createDocumentType(Request $request) {
    // Get provider.
    $provider = $this->requireProvider();

    // Parse JSON.
    $params = $this->getRequestContent($request);

    // Make sure endpoints are fresh.
    // @todo necessary? We already refresh after creating the document type.
    $this->rebuildEndpoints();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Create document type.
    $node_type = $manager->createDocumentType($params);

    // Rebuild endpoints.
    $this->rebuildEndpoints();

    // Reset the list of accessible document types.
    $this->rebuildAccessibleResourceTypes('node_type');

    // Invalidate cache.
    Cache::invalidateTags(['document_types']);

    $data = [
      'message' => 'Document type created',
    ] + $this->buildDocumentTypeJsonOutput($node_type);

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Update document type.
   *
   * @param string $type
   *   Document type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   *
   * @todo consolidate logic with VocabularyController::updateVocabulary().
   */
  public function updateDocumentType($type, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Make sure endpoints are fresh.
    // @todo necessary? We already refresh after updating the document type.
    $this->rebuildEndpoints();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Update the node type.
    $node_type = $manager->updateDocumentType($node_type->id(), $params);

    // Rebuild endpoints.
    $this->rebuildEndpoints();

    // Reset the list of accessible document types.
    $this->rebuildAccessibleResourceTypes('node_type');

    // Invalidate cache.
    Cache::invalidateTags(['document_types']);

    // Response data.
    //
    // @todo for other resources, we only return the "Resource updated" message
    // and the resource uuid. Check if we should do the same here.
    $data = [
      'message' => 'Document type updated',
    ] + $this->buildDocumentTypeJsonOutput($node_type);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete document type.
   *
   * @param string $type
   *   Document type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function deleteDocumentType($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    // Delete the node type.
    $node_type->delete();

    // Rebuild endpoints.
    $this->rebuildEndpoints();

    // Reset the list of accessible document types.
    $this->rebuildAccessibleResourceTypes('node_type');

    // Invalidate cache.
    Cache::invalidateTags(['document_types']);

    // Delete index.
    $index = Index::load('documents_' . $type);
    $index->delete();

    $data = [
      'message' => strtr('@type deleted', ['@type' => $node_type->label()]),
      'uuid' => $node_type->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get document fields.
   *
   * @param string $type
   *   Document type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of vocabularies.
   */
  public function getDocumentFields($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Get the document fields.
    $data = $manager->getDocumentFields();

    // Add cache.
    $cache = $this->createResponseCache()->addCacheTags(['document_fields']);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create document field.
   *
   * @param string $type
   *   Document type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function createDocumentField($type, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Create the field.
    $field_name = $manager->addDocumentField($params);

    // Invalidate cache.
    Cache::invalidateTags(['document_fields']);

    $data = [
      'message' => 'Field created',
      'field_name' => $field_name,
    ];

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Get document field.
   *
   * @param string $type
   *   Document type.
   * @param string $id
   *   Field id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the field data.
   */
  public function getDocumentField($type, $id, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Get the field.
    $data = $manager->getDocumentField($id);

    // Add cache.
    $cache = $this->createResponseCache()->addCacheTags(['document_fields']);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Update document field.
   *
   * @param string $type
   *   Document type.
   * @param string $id
   *   Field id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function updateDocumentField($type, $id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Update field.
    $field_name = $manager->updateDocumentField($id, $params);

    // Invalidate cache.
    Cache::invalidateTags(['document_fields']);

    $data = [
      'message' => 'Field updated',
      'field_name' => $field_name,
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete document field.
   *
   * @param string $type
   *   Document type.
   * @param string $id
   *   Field id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function deleteDocumentField($type, $id, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Delete field.
    $field_name = $manager->deleteDocumentField($id);

    // Invalidate cache.
    Cache::invalidateTags(['document_fields']);

    $data = [
      'message' => 'Field deleted',
      'field_name' => $field_name,
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Load a node type entity.
   *
   * @param string $id
   *   Node type uuid or machine_name.
   *
   * @return \Drupal\node\Entity\NodeType
   *   Node type entity.
   */
  protected function loadNodeType($id) {
    /** @var \Drupal\node\Entity\NodeType */
    return $this->loadResourceEntity('node_type', $id);
  }

  /**
   * Build the document type data for the response.
   *
   * @param \Drupal\node\Entity\NodeType $node_type
   *   Full node type.
   *
   * @return array
   *   Associative array with the document type details.
   */
  protected function buildDocumentTypeJsonOutput(NodeType $node_type) {
    return [
      'machine_name' => $node_type->id(),
      'label' => $node_type->label(),
      'shared' => $node_type->getThirdPartySetting('docstore', 'shared'),
      'private' => $node_type->getThirdPartySetting('docstore', 'private'),
      'content_allowed' => $node_type->getThirdPartySetting('docstore', 'content_allowed'),
      'fields_allowed' => $node_type->getThirdPartySetting('docstore', 'fields_allowed'),
      'provider_uuid' => $node_type->getThirdPartySetting('docstore', 'provider_uuid'),
      'author' => $node_type->getThirdPartySetting('docstore', 'author'),
      'allow_duplicates' => $node_type->getThirdPartySetting('docstore', 'allow_duplicates'),
      'use_revisions' => $node_type->shouldCreateNewRevision(),
      'endpoint' => $node_type->getThirdPartySetting('docstore', 'endpoint'),
    ];
  }

}
