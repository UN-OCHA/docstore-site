<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\docstore\DocumentTypeTrait;
use Drupal\docstore\ManageFields;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\node\Entity\NodeType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config,
      EntityFieldManagerInterface $entityFieldManager,
      EntityTypeManagerInterface $entityTypeManager,
      LoggerChannelFactoryInterface $logger_factory
    ) {
    $this->config = $config;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Create document type.
   */
  public function createDocumentType(Request $request) {
    // Get provider.
    $provider = $this->requireProvider();

    // Parse JSON.
    $params = $this->getRequestContent($request);

    // Make sure endpoints are fresh.
    $this->rebuildEndpoints();

    $manager = new ManageFields($provider, '', $this->entityFieldManager);
    $data = $this->buildJsonOutput($manager->createDocumentType($params));

    // Rebuild endpoints.
    $this->rebuildEndpoints();

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Get document types.
   */
  public function getDocumentTypes(Request $request) {
    // Get provider.
    $provider = $this->requireProvider();

    $data = [];

    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    /** @var \Drupal\node\Entity\NodeType $node_type */
    foreach ($node_types as $node_type) {
      // Skip private types.
      if ($node_type->getThirdPartySetting('docstore', 'private') && $node_type->getThirdPartySetting('docstore', 'provider_uuid') !== $provider->uuid()) {
        continue;
      }

      $data[] = $this->buildJsonOutput($node_type);
    }

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get document type.
   */
  public function getDocumentType($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    $data = $this->buildJsonOutput($node_type);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Update document type.
   */
  public function updateDocumentType($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    // Get provider.
    $provider = $this->requireProvider();

    // Parse JSON.
    $params = $this->getRequestContent($request);

    // Make sure endpoints are fresh.
    $this->rebuildEndpoints();

    $manager = new ManageFields($provider, '', $this->entityFieldManager);
    $data = $this->buildJsonOutput($manager->updateDocumentType($node_type->id(), $params));

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete document type.
   */
  public function deleteDocumentType($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    $data = [
      'message' => strtr('@type deleted', ['@type' => $node_type->label()]),
    ];

    $node_type->delete();

    // Rebuild endpoints.
    $this->rebuildEndpoints();

    // Keep track of private types.
    $this->rebuildDocumentTypes($this->provider);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get document fields.
   */
  public function getDocumentFields($type) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    // Load provider.
    $provider = $this->requireProvider();

    // Create field.
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->database);
    $data = $manager->getDocumentFields();

    // Add cache tags.
    $cache = [
      'tags' => [
        'document_fields',
      ],
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create document field.
   */
  public function createDocumentField($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    // Parse JSON.
    $params = $this->getRequestContent($request);

    // Load provider.
    $provider = $this->requireProvider();

    // Create field.
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->database);
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

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Get document field.
   */
  public function getDocumentField($type, $id, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    // Get provider.
    $provider = $this->requireProvider();

    // Get field config.
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->database);

    try {
      $data = $manager->getDocumentField($id);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Add cache tags.
    $cache = [
      'tags' => [
        'document_fields',
      ],
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Update document field.
   */
  public function updateDocumentField($type, $field, $id, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    // Parse JSON.
    $params = $this->getRequestContent($request);

    // Load provider.
    $provider = $this->requireProvider();

    // Get manager.
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->database);

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

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete document field.
   */
  public function deleteDocumentField($type, $id, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadNodeType($type);

    // Get provider.
    $provider = $this->requireProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, $node_type->id(), $this->entityFieldManager, $this->database);

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

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Build JSON data.
   *
   * @param \Drupal\node\Entity\NodeType $node_type
   *   Full node type.
   *
   * @return array
   *   Associative array with the document type details.
   */
  protected function buildJsonOutput(NodeType $node_type) {
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
