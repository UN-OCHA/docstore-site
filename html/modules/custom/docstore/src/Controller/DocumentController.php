<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\Core\State\State;
use Drupal\docstore\DocumentTypeTrait;
use Drupal\docstore\FileTrait;
use Drupal\docstore\MetadataTrait;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\docstore\RevisionableResourceTrait;
use Drupal\docstore\SearchableResourceTrait;
use Drupal\entity_usage\EntityUsage;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * Controller for API endpoints.
 */
class DocumentController extends ControllerBase {

  use DocumentTypeTrait;
  use FileTrait;
  use MetadataTrait;
  use ProviderTrait;
  use ResourceTrait;
  use RevisionableResourceTrait;
  use SearchableResourceTrait;

  /**
   * The config factory.
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
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The file usage.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
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
  public function __construct(ConfigFactoryInterface $configFactory,
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
    $this->configFactory = $configFactory;
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
   * Get documents.
   *
   * @param string $type
   *   Document type, "any" or "all".
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of resources.
   */
  public function getDocuments($type, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'read');

    // Load the node type if not a request against all types of documents.
    $node_type = $type !== 'any' ? $this->loadDocumentType($type) : NULL;

    // Get the documents.
    return $this->searchResources($request, 'node', $node_type);
  }

  /**
   * Get all document files.
   *
   * @param string $type
   *   Document type, "any" or "all".
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of resources.
   */
  public function getAllDocumentFiles($type, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'read');

    // Load the node type if not a request against all types of documents.
    $node_type = $type !== 'any' ? $this->loadDocumentType($type) : NULL;

    // Get the documents.
    return $this->searchResources($request, 'node', $node_type, NULL, NULL, TRUE);
  }

  /**
   * Get a single document.
   *
   * @param string $type
   *   Document type, "any" or "all".
   * @param string $id
   *   Document id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the resource's data.
   */
  public function getDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'read');

    // Load the node type if not a request against all types of documents.
    $node_type = $type !== 'any' ? $this->loadDocumentType($type) : NULL;

    // Get the document's data.
    return $this->searchResources($request, 'node', $node_type, $id);
  }

  /**
   * Get document revisions.
   *
   * @param string $type
   *   Document type, "any" or "all".
   * @param string $id
   *   Document id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the resource'data including the list of revisions.
   *
   * @todo Should this return only the list of revisions?
   */
  public function getDocumentRevisions($type, $id, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'read');

    // Load the node type if not a request against all types of documents.
    $node_type = $type !== 'any' ? $this->loadDocumentType($type) : NULL;

    // Get the document's data including the revision list.
    return $this->searchResources($request, 'node', $node_type, $id, TRUE);
  }

  /**
   * Get 1 document revision.
   *
   * @param string $type
   *   Document type, "any" or "all".
   * @param string $id
   *   Document id or uuid.
   * @param string $vid
   *   Revision id or "last".
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the resource revision's data.
   */
  public function getDocumentRevision($type, $id, $vid, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'read');

    // Load the node type if not a request against all types of documents.
    $node_type = $type !== 'any' ? $this->loadDocumentType($type) : NULL;

    // Get the provider.
    $provider = $this->getProvider();

    // Load the revision.
    $revision = $this->loadResourceEntityRevision($id, $vid, 'node', $node_type, $provider);

    // Prepare the response data.
    $data = $this->prepareEntityResourceDataForResponse($revision, $provider);

    // Add cache contexts and tags.
    $cache = $this->createResponseCache()
      ->addCacheTags(['documents'])
      ->addCacheableDependency($revision);

    if (isset($node_type)) {
      $cache->addCacheableDependency($node_type);
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get document files.
   *
   * @param string $type
   *   Document type, "any" or "all".
   * @param string $id
   *   Document id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the resource's list of files.
   */
  public function getDocumentFiles($type, $id, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'read');

    // Load the node type if not a request against all types of documents.
    $node_type = $type !== 'any' ? $this->loadDocumentType($type) : NULL;

    // Get the document's data.
    return $this->searchResources($request, 'node', $node_type, $id, FALSE, TRUE);
  }

  /**
   * Get document terms.
   *
   * @param string $type
   *   Document type, "any" or "all".
   * @param string $id
   *   Document id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException
   *   412 Precondition Failed because not implemented yet.
   *
   * @todo Implement or remove.
   */
  public function getDocumentTerms($type, $id, Request $request) {
    // Check if type is allowed.
    $this->typeAllowed($type, 'read');

    throw new PreconditionFailedHttpException('Not implemented (yet)');
  }

  /**
   * Process documents (create, update, delete) in bulk.
   *
   * @param string $type
   *   Document type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   API response.
   */
  public function processDocumentsInBulk($type, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadDocumentType($type);

    // Get the provider.
    $provider = $this->requireProvider();

    // Check if the provider can create/update/delete this type of documents.
    $this->providerCanCreateUpdateDelete($node_type, $provider);

    // @todo move all those checks in a separate class to validate request
    // content.
    //
    // Check that the author property is set.
    if (empty($params['author']) || !is_string($params['author'])) {
      throw new BadRequestHttpException('The "author" property is required and must be a string');
    }
    $author = $params['author'];

    // Check that the list of terms is present.
    if (empty($params['documents']) || !is_array($params['documents'])) {
      throw new BadRequestHttpException('The "documents" property is required and must be an array.');
    }

    $data = [];
    $method = $request->getMethod();
    foreach ($params['documents'] as $document) {
      try {
        switch ($method) {
          case 'POST':
            // We only add the author when creating terms as it cannot be
            // changed afterwards.
            $document['author'] = $author;
            $data[] = $this->createDocumentFromParameters($node_type, $document, $provider);
            break;

          case 'PUT':
            $data[] = $this->updateDocumentFromParameters($node_type, $document, $provider, TRUE);
            break;

          case 'PATCH':
            $data[] = $this->updateDocumentFromParameters($node_type, $document, $provider, FALSE);
            break;

          case 'DELETE':
            $data[] = $this->deleteDocumentFromParameters($node_type, $document, $provider);
            break;

          default:
            throw new BadRequestHttpException('Unrecognized bulk operation');
        }
      }
      catch (\Exception $exception) {
        $code = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
        $data[] = [
          'error' => [
            'status' => $code,
            'message' => $exception->getMessage(),
          ],
        ];
      }

    }

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Create document.
   *
   * @param string $type
   *   Document type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   API response.
   */
  public function createDocument($type, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadDocumentType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Create document.
    $data = $this->createDocumentFromParameters($node_type, $params, $provider, TRUE);

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Create document from the given parameters.
   *
   * @param \Drupal\node\Entity\NodeType $node_type
   *   Node type entity.
   * @param array $params
   *   Parameters to create the document with.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $full_output
   *   Whether to return the full document's data or only the uuid.
   *
   * @return array
   *   Associative array with the document uuid and a "Doctype created" message.
   */
  protected function createDocumentFromParameters(NodeType $node_type, array $params, UserInterface $provider, $full_output = FALSE) {
    // Check if provider can create documents.
    $this->providerCanCreateUpdateDelete($node_type, $provider);

    // Check required fields.
    if (empty($params['title'])) {
      throw new BadRequestHttpException('Title is required');
    }
    if (empty($params['author'])) {
      throw new BadRequestHttpException('Author is required');
    }

    // Create node.
    $item = [
      'type' => $node_type->id(),
      'title' => $params['title'],
      'uid' => $provider->id(),
      'author' => $params['author'],
      'files' => [],
      'private' => [],
      'status' => Node::PUBLISHED,
    ];

    // Published.
    if (isset($params['published'])) {
      $item['status'] = empty($params['published']) ? Node::NOT_PUBLISHED : Node::PUBLISHED;
    }

    // Private.
    if (isset($params['private'])) {
      $item['private'] = !empty($params['private']);
    }

    // Attach files.
    if (!empty($params['files'])) {
      $files = $params['files'];
      if (!is_array($files)) {
        $files = [$files];
      }

      // Allow file uuid or file name.
      foreach ($files as $file) {
        $uuid = NULL;

        // Assume it's a uuid.
        if (is_string($file)) {
          $uuid = $file;
        }
        elseif (is_array($file)) {
          if (isset($file['uuid'])) {
            $uuid = $file['uuid'];
          }
          // @todo this is for backward compatibility, remove.
          elseif (isset($file['media_uuid'])) {
            $uuid = $file['media_uuid'];
          }
          elseif (isset($file['uri'])) {
            $media = $this->fetchAndCreateFile($file['uri'], $provider);
            $item['files'][] = [
              'target_uuid' => $media->uuid(),
            ];
          }
          elseif (isset($file['filename'])) {
            $content = $this->fetchDropfolderFileContent($file['filename'], $provider);
            $file = $this->createFileEntity($file['filename'], 'undefined', FALSE, $provider);
            $file = $this->saveFileToDisk($file, $content, $provider);
            $media = $this->createMediaEntity($file, FALSE, $provider);
            $this->saveMedia($media, $file, $provider);

            $item['files'][] = [
              'target_uuid' => $media->uuid(),
            ];
          }
        }

        if (!empty($uuid)) {
          /** @var \Drupal\media\Entity\Media $media */
          $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
          if (empty($media)) {
            throw new BadRequestHttpException(strtr('Media @uuid does not exist', ['@uuid' => $uuid]));
          }

          $item['files'][] = [
            'target_uuid' => $file['uuid'],
          ];
        }
      }
    }

    // Add all other fields.
    $item = array_merge($item, $this->buildItemDataFromParams($params, 'node', $node_type->id(), $provider, $params['author']));

    /** @var \Drupal\node\Entity\Node $document */
    $document = Node::create($item);

    // Check for invalid fields.
    foreach ($item as $key => $data) {
      if (!$document->hasField($key)) {
        throw new BadRequestHttpException(strtr('Unknown field @field', [
          '@field' => $key,
        ]));
      }
    }

    // Create a new revision if necessary.
    $this->createEntityRevisionFromParameters($document, $params, $provider);

    // Validate and save the document.
    $this->validateAndSaveEntity($document);

    // Invalidate cache.
    Cache::invalidateTags(['documents']);

    return [
      'message' => strtr('@type created', ['@type' => $node_type->label()]),
    ] + $this->prepareEntityResourceDataForResponse($document, $provider, $full_output);
  }

  /**
   * Update document.
   *
   * @param string $type
   *   Document type.
   * @param string $id
   *   Document uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   API response.
   */
  public function updateDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadDocumentType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Pass the document id to load the document.
    $params['id'] = $id;

    // Update the document.
    $data = $this->updateDocumentFromParameters($node_type, $params, $provider, $request->getMethod() === 'PUT');

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Update a document from a set of parameters.
   *
   * @param \Drupal\node\Entity\NodeType $node_type
   *   Document type.
   * @param array $params
   *   Paramaters to update the document with.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $full_update
   *   Whether to perform a full or partial update of the document.
   *
   * @return array
   *   Associative array with the document uuid and a "Doctype updated" message.
   */
  public function updateDocumentFromParameters(NodeType $node_type, array $params, UserInterface $provider, $full_update = TRUE) {
    // Check if the provider can create/update/delete this type of documents.
    $this->providerCanCreateUpdateDelete($node_type, $provider);

    // Load document.
    $document_id = $params['uuid'] ?? $params['id'] ?? '';
    if (empty($document_id)) {
      throw new BadRequestHttpException('Document id is required');
    }
    $document = $this->loadDocument($document_id);
    unset($params['uuid']);
    unset($params['id']);

    // Remove the author as it cannot be changed.
    unset($params['author']);

    // Make sure the node belongs to the node type.
    $this->validateEntityBundle($document, $node_type);

    // A document can only be updated by its owner.
    $this->providerIsOwner($document, $provider);

    // Check required fields.
    if ($full_update) {
      if (empty($params['title'])) {
        throw new BadRequestHttpException('Title is required');
      }
    }

    // Re-map fields.
    // @todo check that it's a boolean.
    if (isset($params['private'])) {
      $document->set('private', $params['private']);
      unset($params['private']);
    }

    // @todo check that it's a boolean.
    if (isset($params['published'])) {
      $document->setPublished($params['published']);
      unset($params['published']);
    }

    // Update the document fields from the given parameters.
    $updated_fields = $this->updateEntityFieldsFromParameters($document, $params, $provider);

    // Reset all the fields that were not updated.
    if ($full_update) {
      $this->resetEntityFields($document, $provider, $updated_fields);
    }

    // Create a new revision if necessary.
    $this->createEntityRevisionFromParameters($document, $params, $provider);

    // Validate and save the document.
    $this->validateAndSaveEntity($document);

    // Invalidate cache.
    Cache::invalidateTags(['documents']);

    return [
      'message' => strtr('@type updated', ['@type' => $node_type->label()]),
      'uuid' => $document->uuid(),
    ];
  }

  /**
   * Publish a document revision.
   *
   * @param string $type
   *   Document type.
   * @param string $id
   *   Document id or uuid.
   * @param string $vid
   *   Revision id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the update message and document uuid.
   *
   * @todo provide a way to unpublish a revision.
   */
  public function publishDocumentRevision($type, $id, $vid, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadDocumentType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\node\Entity\Node $revision */
    $revision = $this->loadResourceEntityRevision($id, $vid, 'node', $node_type, $provider);

    // A document's revision can only be updated by its owner.
    $this->providerIsOwner($revision, $provider);

    // Publish the revision.
    $this->publishEntityRevisionFromParameters($revision, $params, $provider);

    $data = [
      'message' => strtr('@type revision published', [
        '@type' => $node_type->label(),
      ]),
      'uuid' => $revision->uuid(),
    ];

    // Add cache.
    $cache = $this->createResponseCache()->addCacheableDependency($revision);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Delete document.
   *
   * @param string $type
   *   Document type.
   * @param string $id
   *   Document uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   API response.
   */
  public function deleteDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->loadDocumentType($type);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Pass the document id to load the document.
    $params['id'] = $id;

    // Delete the document.
    $data = $this->deleteDocumentFromParameters($node_type, $params, $provider);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete a node from provided parameters.
   *
   * @param \Drupal\node\Entity\NodeType $node_type
   *   Node type.
   * @param array $params
   *   Parameters to delete the term with.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   Associative array with the node uuid and a "Doctype deleted" message.
   */
  public function deleteDocumentFromParameters(NodeType $node_type, array $params, UserInterface $provider) {
    // Check if provider can create documents.
    $this->providerCanCreateUpdateDelete($node_type, $provider);

    // Load document.
    $document_id = $params['uuid'] ?? $params['id'] ?? '';
    if (empty($document_id)) {
      throw new BadRequestHttpException('Document id is required');
    }
    $document = $this->loadDocument($document_id);

    // Make sure the node belongs to the node type.
    $this->validateEntityBundle($document, $node_type);

    // A document can only be deleted by its owner.
    $this->providerIsOwner($document, $provider);

    // Check if document is in use.
    // @todo discuss if this should be removed.
    if ($this->entityInUse($document)) {
      throw new BadRequestHttpException('Document is referenced elsewhere and can not be deleted');
    }

    // Delete the document.
    $document->delete();

    // Invalidate cache.
    Cache::invalidateTags(['documents']);

    return [
      'message' => strtr('@type deleted', ['@type' => $node_type->label()]),
      'uuid' => $document->uuid(),
    ];
  }

  /**
   * Load a document type.
   *
   * @param string $id
   *   Node type uuid or machine_name.
   *
   * @return \Drupal\node\Entity\NodeType
   *   Node type entity.
   */
  protected function loadDocumentType($id) {
    /** @var \Drupal\node\Entity\NodeType */
    return $this->loadResourceEntity('node_type', $id);
  }

  /**
   * Load a document.
   *
   * @param string $id
   *   The document id or uuid.
   *
   * @return \Drupal\node\Entity\Node
   *   Document node.
   */
  protected function loadDocument($id) {
    /** @var \Drupal\node\Entity\Node */
    return $this->loadResourceEntity('node', $id);
  }

}
