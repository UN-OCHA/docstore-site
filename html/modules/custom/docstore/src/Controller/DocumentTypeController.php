<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\docstore\DocumentTypeTrait;
use Drupal\docstore\ManageFields;
use Drupal\docstore\ProviderTrait;
use Drupal\node\Entity\NodeType;
use Nette\NotImplementedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller for document type endpoints.
 */
class DocumentTypeController extends ControllerBase {

  use DocumentTypeTrait;
  use ProviderTrait;

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
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Make sure endpoints are fresh.
    $this->rebuildEndpoints();

    $manager = new ManageFields($provider, '', $this->entityFieldManager);
    $data = $this->buildJsonOutput($manager->createDocumentType($params));

    // Rebuild endpoints.
    $this->rebuildEndpoints();

    $response = new JsonResponse($data);
    $response->setStatusCode(201);
    return $response;
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

    $response = new JsonResponse($data);
    return $response;
  }

  /**
   * Get document type.
   */
  public function getDocumentType($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);

    if (!$node_type) {
      throw new BadRequestHttpException('Type not supported.');
    }

    $data = $this->buildJsonOutput($node_type);

    $response = new JsonResponse($data);
    return $response;
  }

  /**
   * Update document type.
   */
  public function updateDocumentType($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);

    if (!$node_type) {
      throw new BadRequestHttpException('Type not supported.');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Make sure endpoints are fresh.
    $this->rebuildEndpoints();

    $manager = new ManageFields($provider, '', $this->entityFieldManager);
    $data = $this->buildJsonOutput($manager->updateDocumentType($type, $params));

    $response = new JsonResponse($data);
    $response->setStatusCode(200);
    return $response;
  }

  /**
   * Delete document type.
   */
  public function deleteDocumentType($type, Request $request) {
    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);

    if (!$node_type) {
      throw new BadRequestHttpException('Type not supported.');
    }

    $data = [
      'message' => strtr('@type deleted', ['@type' => $node_type->label()]),
    ];

    $node_type->delete();

    // Rebuild endpoints.
    $this->rebuildEndpoints();

    $response = new JsonResponse($data);
    return $response;
  }

  /**
   * Build JSON data.
   *
   * @param \Drupal\node\Entity\NodeType $node_type
   *   Full node type.
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
      'endpoint' => $node_type->getThirdPartySetting('docstore', 'endpoint'),
    ];
  }

}
