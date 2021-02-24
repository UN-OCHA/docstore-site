<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Uuid\Uuid;
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
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\docstore\DocumentTypeTrait;
use Drupal\docstore\MetadataTrait;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\entity_usage\EntityUsage;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for API endpoints.
 */
class DocumentController extends ControllerBase {

  use DocumentTypeTrait;
  use MetadataTrait;
  use ProviderTrait;
  use ResourceTrait;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
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
   * Create document.
   */
  public function createDocument($type, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    // Get provider.
    $provider = $this->requireProvider();

    // Parse JSON.
    $params = $this->getRequestContent($request);

    // Create document.
    $document = $this->createDocumentForProvider($type, $params, $provider);

    $data = [
      'message' => strtr('@type created', ['@type' => $this->getNodeTypeLabel($type)]),
      'uuid' => $document->uuid(),
    ];

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Create document for provider.
   */
  protected function createDocumentForProvider($type, $params, $provider) {
    // Check if provider can create documents.
    $this->providerCanCreateUpdateDelete($this->getNodeType($type));

    // Check required fields.
    if (empty($params['title'])) {
      throw new BadRequestHttpException('Title is required');
    }

    if (empty($params['author'])) {
      throw new BadRequestHttpException('Author is required');
    }

    // Create node.
    $item = [
      'type' => $type,
      'title' => $params['title'],
      'uid' => $provider->id(),
      'author' => [],
      'files' => [],
      'private' => [],
      'status' => Node::PUBLISHED,
    ];

    // Published.
    if (isset($params['published']) && !$params['published']) {
      $item['status'] = Node::NOT_PUBLISHED;
    }

    // Private.
    if (isset($params['private']) && $params['private']) {
      $item['private'][] = [
        'value' => TRUE,
      ];
    }
    else {
      $item['private'][] = [
        'value' => FALSE,
      ];
    }

    // Store HID Id.
    $item['author'][] = [
      'value' => $params['author'],
    ];

    // Attach files.
    if (isset($params['files']) && $params['files']) {
      $files = $params['files'];
      if (!is_array($files)) {
        $files = [$files];
      }

      // Allow file uuid or file name.
      foreach ($files as $uuid) {
        if (is_string($uuid)) {
          /** @var \Drupal\media\Entity\Media $media */
          $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
          if (!$media) {
            throw new BadRequestHttpException(strtr('Media @uuid does not exist', ['@uuid' => $uuid]));
          }

          $item['files'][] = [
            'target_uuid' => $media->uuid(),
          ];
        }
        else {
          if (isset($uuid['uri'])) {
            $media = $this->fetchAndCreateFile($uuid['uri'], $provider);
            $item['files'][] = [
              'target_uuid' => $media->uuid(),
            ];
          }
        }
      }
    }

    // Check for meta tags.
    if (isset($params['metadata']) && $params['metadata']) {
      $metadata = $params['metadata'];
      $item = array_merge($item, $this->buildItemDataFromMetaData($metadata, $type, $provider, $params['author'], 'node'));
    }

    /** @var \Drupal\node\Entity\Node $document */
    $document = Node::create($item);

    // Trigger validation.
    $violations = $document->validate();
    if (count($violations) > 0) {
      throw new BadRequestHttpException(strtr('Unable to save document: @error (@path)', [
        '@error' => strip_tags($violations->get(0)->getMessage()),
        '@path' => $violations->get(0)->getPropertyPath(),
      ]));
    }

    // Save document.
    $document->save();

    // Invalidate cache.
    Cache::invalidateTags(['documents']);

    return $document;
  }

  /**
   * Fetch and create a file.
   */
  protected function fetchAndCreateFile($uri, $provider) {
    $content = file_get_contents($uri);

    // Create URI.
    $destination = $this->config('system.file')->get('default_scheme') . '://';

    $destination .= 'files/';
    $destination .= substr(md5(basename($uri)), 0, 3);
    $destination .= '/' . substr(md5(basename($uri)), 3, 3);
    $destination .= '/' . basename($uri);

    // Store files in sub directories.
    $file = File::create();
    $file->setOwnerId($provider->id());
    $file->setFileName(basename($uri));
    $file->setFileUri($destination);
    $file->setTemporary();

    $media = $this->saveFileToDisk($file, $content, $provider, FALSE);

    return $media;
  }

  /**
   * Create document.
   */
  public function createDocumentInBulk($type, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    // Get provider.
    $provider = $this->requireProvider();

    // Parse JSON.
    $params = $this->getRequestContent($request);

    if (empty($params['documents'])) {
      throw new BadRequestHttpException('documents is required');
    }

    $data = [];
    foreach ($params['documents'] as $document) {
      // Add common fields.
      $document['author'] = $params['author'];

      // Create document.
      $doc = $this->createDocumentForProvider($type, $document, $provider);

      $data[] = [
        'message' => strtr('@type created', ['@type' => $this->getNodeTypeLabel($type)]),
        'uuid' => $doc->uuid(),
      ];
    }

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Update document.
   */
  public function updateDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    $protected_fields = [
      'author',
      'provider_uuid',
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
      'promote',
      'sticky',
      'type',
      'nid',
      'uuid',
      'vid',
      'uid',
    ];

    // Load document.
    $document = $this->loadDocument($id);
    if (!$document) {
      throw new NotFoundHttpException(strtr('Document @uuid does not exist', ['@uuid' => $id]));
    }

    // Parse JSON.
    $params = $this->getRequestContent($request);

    // A document can only be updated by its owner.
    $this->providerIsOwner($document);

    // Check required fields.
    if ($request->getMethod() === 'PUT') {
      if (empty($params['title'])) {
        throw new BadRequestHttpException('Title is required');
      }
    }

    // Re-map fields.
    if (isset($params['private'])) {
      $document->set('private', $params['private']);
      unset($params['private']);
    }

    if (isset($params['published'])) {
      $document->setPublished($params['published']);
      unset($params['published']);
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

          if ($document->hasField($name)) {
            $document->set($name, $values);
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
      // Ignore revision fields.
      if ($name === 'new_revision' || $name === 'revision_log' || $name === 'draft') {
        continue;
      }

      // Make sure protected fields aren't set.
      if (isset($protected_fields[$name])) {
        throw new BadRequestHttpException(strtr('Field @name cannot be changed', ['@name' => $name]));
      }

      if ($document->hasField($name)) {
        if (is_array($values)) {
          $massaged = [];
          foreach ($values as &$value) {
            if (isset($value['uuid'])) {
              $massaged[] = $value['uuid'];
            }
            elseif (isset($value['media_uuid'])) {
              $massaged[] = $value['media_uuid'];
            }
            else {
              $massaged[] = $value;
            }
          }
          $document->set($name, $massaged);
        }
        else {
          $document->set($name, $values);
        }

        $updated_fields[] = $name;
      }
      else {
        throw new BadRequestHttpException(strtr('Field @name does not exists', ['@name' => $name]));
      }
    }

    // Remove all fields not part of params.
    if ($request->getMethod() === 'PUT') {
      $document_fields = $document->getFields(FALSE);
      foreach ($document_fields as $document_field) {
        // Skip name field.
        if ($document_field->getName() === 'title') {
          continue;
        }

        if (in_array($document_field->getName(), $updated_fields)) {
          continue;
        }

        if (in_array($document_field->getName(), $protected_fields)) {
          continue;
        }

        if (!$document_field->isEmpty()) {
          $document->set($document_field->getName(), NULL);
        }
      }
    }

    // Check if we need to create a new revision.
    $document->setRevisionCreationTime(time());

    /** @var \Drupal\node\Entity\NodeType $node_type */
    $node_type = $this->getNodeType($type);

    // Load provider.
    $provider = $this->getProvider();
    if ($node_type->isNewRevision() || $params['new_revision'] ?? FALSE) {
      $document->revision_log = 'Updated';
      if (isset($params['revision_log'])) {
        $document->revision_log = $params['revision_log'];
        unset($params['revision_log']);
      }
      $document->setNewRevision();
      $document->setRevisionUserId($provider->id());

      // Save new revision as draft?
      $document->isDefaultRevision(TRUE);
      if ($params['draft'] ?? FALSE) {
        $document->isDefaultRevision(FALSE);
      }
    }

    // Trigger validation.
    $violations = $document->validate();
    if (count($violations) > 0) {
      throw new BadRequestHttpException(strtr('Unable to save document: @error (@path)', [
        '@error' => strip_tags($violations->get(0)->getMessage()),
        '@path' => $violations->get(0)->getPropertyPath(),
      ]));
    }

    // Save document.
    $document->save();

    $data = [
      'message' => strtr('@type updated', ['@type' => $this->getNodeTypeLabel($type)]),
      'uuid' => $document->uuid(),
    ];

    // Invalidate cache.
    Cache::invalidateTags(['documents']);

    // Add cache tags.
    $cache = [
      'tags' => $document->getCacheTags(),
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Delete document.
   */
  public function deleteDocument($type, $id, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    // Load document.
    $document = $this->loadDocument($id);

    // A document can only be deleted by its owner.
    $this->providerIsOwner($document);

    $data = [
      'message' => strtr('@type deleted', ['@type' => $this->getNodeTypeLabel($type)]),
      'uuid' => $document->uuid(),
    ];

    $document->delete();

    // Invalidate cache.
    Cache::invalidateTags(['documents']);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Publish a document revision.
   */
  public function publishDocumentRevision($type, $id, $vid, Request $request) {
    // Check if type is allowed.
    $type = $this->typeAllowed($type, 'write');

    // Parse JSON.
    $params = $this->getRequestContent($request);

    // Get provider.
    $provider = $this->requireProvider();

    // Check for last.
    if ($vid === 'last') {
      // Get last revisions.
      $query = $this->database->select('node_revision', 'nr')
        ->fields('nr', ['vid']);
      $query->innerJoin('node', 'n', 'nr.nid = n.nid');
      $vid = $query->condition('n.uuid', $id)
        ->orderBy('vid', 'DESC')
        ->execute()
        ->fetchCol(0);

      $vid = reset($vid);
    }

    /** @var \Drupal\node\Entity\Node $document */
    $document = $this->entityTypeManager->getStorage('node')->loadRevision($vid);

    // A document can only be updated by its owner.
    $this->providerIsOwner($document);

    if ($document->uuid() !== $id) {
      throw new NotFoundHttpException('Revision not found');
    }

    if ($document->bundle() !== $type) {
      throw new BadRequestHttpException('Wrong document type');
    }

    if (!$document->isDefaultRevision()) {
      $document->setRevisionCreationTime(time());
      $document->revision_log = 'Updated';
      if (isset($params['revision_log'])) {
        $document->revision_log = $params['revision_log'];
      }
      $document->setNewRevision();
      $document->setRevisionUserId($provider->id());

      $document->isDefaultRevision(TRUE);
      $document->save();
    }

    $data = [
      'message' => strtr('@type updated', ['@type' => $this->getNodeTypeLabel($type)]),
      'uuid' => $document->uuid(),
    ];

    // Add cache tags.
    $cache = [
      'tags' => $document->getCacheTags(),
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
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

    return $this->createJsonResponse($data, 200);
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

    // If the file is private check if the provider is its owner.
    if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
      $this->providerIsOwner($file);
    }

    $data = [
      'uuid' => $media->uuid(),
      'name' => $media->getName(),
      'created' => date(DATE_ATOM, $media->getCreatedTime()),
      'changed' => date(DATE_ATOM, $media->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'file_uuid' => $file->uuid(),
      'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
      'revisions' => [],
    ];

    $revisions = $this->entityTypeManager->getStorage('media')->getQuery()
      ->allRevisions()
      ->condition('mid', $media->id())
      ->sort('vid', 'DESC')
      ->pager(50)
      ->execute();

    // Was $data['revisions'] = array_keys($revisions);.
    foreach (array_keys($revisions) as $vid) {
      /** @var \Drupal\media\Entity\Media $revision */
      $revision = $this->entityTypeManager->getStorage('media')->loadRevision($vid);

      /** @var \Drupal\media\Entity\File $file */
      $file = $this->entityTypeManager->getStorage('file')->load($revision->getSource()->getSourceFieldValue($revision));

      // If the file is private check if the provider is its owner.
      if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
        $this->providerIsOwner($file);
      }

      $data['revisions'][] = [
        'vid' => $vid,
        'uuid' => $revision->uuid(),
        'name' => $revision->getName(),
        'created' => date(DATE_ATOM, $revision->getCreatedTime()),
        'changed' => date(DATE_ATOM, $revision->getChangedTime()),
        'mimetype' => $file->getMimeType(),
        'file_uuid' => $file->uuid(),
        'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
      ];
    }

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get media revision.
   */
  public function getMediaRevision($id, $vid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $id);
    if (!$media) {
      throw new BadRequestHttpException('Media does not exist');
    }

    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->entityTypeManager->getStorage('media')->loadRevision($vid);
    if (!$revision) {
      throw new BadRequestHttpException('Revision does not exist');
    }

    if ($revision->id() !== $media->id()) {
      throw new BadRequestHttpException('Illegal revision detected');
    }

    /** @var \Drupal\media\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($revision->getSource()->getSourceFieldValue($revision));

    // If the file is private check if the provider is its owner.
    if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
      $this->providerIsOwner($file);
    }

    $data = [
      'uuid' => $revision->uuid(),
      'name' => $revision->getName(),
      'created' => date(DATE_ATOM, $revision->getCreatedTime()),
      'changed' => date(DATE_ATOM, $revision->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'file_uuid' => $file->uuid(),
      'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get media content.
   */
  public function getMediaContent($id, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $id);
    if (!$media) {
      throw new BadRequestHttpException('Media does not exist');
    }

    /** @var \Drupal\media\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($media->getSource()->getSourceFieldValue($media));

    // If the file is private check if the provider is its owner.
    if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
      $this->providerIsOwner($file);
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($media->getName()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Get media revision content.
   */
  public function getMediaRevisionContent($id, $vid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $id);
    if (!$media) {
      throw new NotFoundHttpException('Media does not exist');
    }

    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->entityTypeManager->getStorage('media')->loadRevision($vid);
    if (!$revision) {
      throw new NotFoundHttpException('Revision does not exist');
    }

    if ($revision->id() !== $media->id()) {
      throw new BadRequestHttpException('Illegal revision detected');
    }

    /** @var \Drupal\media\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($revision->getSource()->getSourceFieldValue($revision));

    // If the file is private check if the provider is its owner.
    if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
      $this->providerIsOwner($file);
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($revision->getName()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Get files.
   */
  public function getFiles(Request $request) {
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple();
    $data = [];

    // Load provider.
    $provider = $this->getProvider();
    $provider_id = (!$provider || $provider->isAnonymous()) ? FALSE : $provider->id();

    /** @var \Drupal\file\Entity\File $file */
    foreach ($files as $file) {
      $file_record = [
        'file' => $file->uuid(),
        'uri' => $request->getSchemeAndHttpHost() . $file->createFileUrl(),
        'created' => date(DATE_ATOM, $file->getCreatedTime()),
        'changed' => date(DATE_ATOM, $file->getChangedTime()),
        'mimetype' => $file->getMimeType(),
      ];

      // Hide private files, unless it's the owner.
      if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
        $file_record['private'] = TRUE;
        if ($file->getOwnerId() !== $provider_id) {
          unset($file_record['uri']);
        }
      }

      $data[] = $file_record;
    }

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Create file.
   */
  public function createFile(Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    // Load provider.
    $provider = $this->requireProvider();

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

    // Support private files.
    $private = FALSE;
    if (isset($params['private']) && $params['private']) {
      $private = TRUE;
    }

    // Create URI.
    $destination = $this->config('system.file')->get('default_scheme') . '://';
    if ($private) {
      $destination = 'private://';
    }

    $destination .= 'files/';
    $destination .= substr(md5($params['filename']), 0, 3);
    $destination .= '/' . substr(md5($params['filename']), 3, 3);
    $destination .= '/' . $params['filename'];

    // Store files in sub directories.
    $file = File::create();
    $file->setOwnerId($provider->id());
    $file->setMimeType($params['mimetype']);
    $file->setFileName($params['filename']);
    $file->setFileUri($destination);
    $file->setTemporary();

    $media = FALSE;
    if (isset($params['data'])) {
      // Decode data.
      $content = base64_decode($params['data']);
      $media = $this->saveFileToDisk($file, $content, $provider, FALSE);
    }
    elseif (isset($params['uri'])) {
      // Fetch file.
      $content = file_get_contents($params['uri']);
      $media = $this->saveFileToDisk($file, $content, $provider, FALSE);
    }
    elseif (isset($params['use_dropfolder']) && $params['use_dropfolder']) {
      if (!$provider->get('dropfolder')->value) {
        throw new BadRequestHttpException('Dropfolder is not enabled');
      }

      $files = $this->fileSystem->scanDirectory($provider->get('dropfolder')->value, '/^' . $params['filename'] . '$/');
      if ($files) {
        $files = array_keys($files);
        $first = reset($files);
        $content = file_get_contents($first);
        $media = $this->saveFileToDisk($file, $content, $provider, FALSE);
      }
      else {
        throw new BadRequestHttpException('File not found in dropfolder');
      }
    }
    else {
      $file->save();
    }

    $data = [
      'message' => 'File created',
      'uuid' => $file->uuid(),
      'filename' => $file->getFilename(),
      'url' => $file->createFileUrl(),
      'created' => date(DATE_ATOM, $file->getCreatedTime()),
      'changed' => date(DATE_ATOM, $file->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'size' => $file->getSize(),
      'private' => $private,
    ];

    if ($media) {
      $data['media_uuid'] = $media->uuid();
    }

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Get file.
   */
  public function getFile($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new NotFoundHttpException('File does not exist');
    }

    // If the file is private check if the provider is its owner.
    if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
      $this->providerIsOwner($file);
    }

    $data = [
      'file' => $file->uuid(),
      'filename' => $file->getFilename(),
      'url' => $file->createFileUrl(),
      'created' => date(DATE_ATOM, $file->getCreatedTime()),
      'changed' => date(DATE_ATOM, $file->getChangedTime()),
      'mimetype' => $file->getMimeType(),
      'size' => $file->getSize(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Update file.
   */
  public function updateFile($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new NotFoundHttpException('File does not exist');
    }

    // A file can only be updated by its owner.
    $this->providerIsOwner($file);

    // Filename is required.
    if (isset($params['filename']) && $params['filename'] !== $file->getFilename()) {
      $file->setFilename($params['filename']);
      $file->save();
    }

    // See if we need to move the file.
    if (isset($params['private'])) {
      $private_state = StreamWrapperManager::getScheme($file->getFileUri()) === 'private';
      $private = FALSE;
      if ($params['private']) {
        $private = TRUE;
      }

      // See if private changed.
      if ($private_state !== $private) {
        // Move file.
        if ($private) {
          $this->moveFileToPrivate($file);
        }
        else {
          $this->moveFileToPublic($file);
        }
      }
    }

    $data = [
      'message' => 'File updated',
    ];

    return $this->createJsonResponse($data, 200);
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

    // A file can only be deleted by its owner.
    $this->providerIsOwner($file);

    $usage_list = $this->fileUsage->listUsage($file);
    $usage_list = isset($usage_list['file']) ? $usage_list['file'] : [];
    if (count($usage_list) > 0) {
      throw new BadRequestHttpException(strtr('File is still in use in @num places', ['@num' => $usage_list]));
    }

    $file->delete();

    $data = [
      'message' => 'File is deleted',
    ];

    return $this->createJsonResponse($data, 200);
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

    // If the file is private check if the provider is its owner.
    if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
      $this->providerIsOwner($file);
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

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get file content.
   */
  public function getFileContent($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    // If the file is private check if the provider is its owner.
    if (StreamWrapperManager::getScheme($file->getFileUri()) === 'private') {
      $this->providerIsOwner($file);
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($file->getFilename()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Create file content.
   */
  public function createFileContent($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $id);
    if (!$file) {
      throw new NotFoundHttpException('File does not exist');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // A file can only be deleted by its owner.
    $this->providerIsOwner($file);

    // @todo add some validation.
    $this->saveFileToDisk($file, $request->getContent(), $provider, TRUE);

    $data = [
      'message' => 'File content created',
      'uuid' => $file->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Update file content.
   */
  public function updateFileContent($id, Request $request) {
    return $this->createFileContent($id, $request);
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
   * Load a document.
   *
   * @param string $id
   *   The document uuid.
   *
   * @return \Drupal\node\Entity\Node
   *   document.
   */
  protected function loadDocument($id) {
    if (Uuid::isValid($id)) {
      $document = $this->entityRepository->loadEntityByUuid('node', $id);
    }

    if (!$document) {
      throw new NotFoundHttpException('Document does not exist');
    }

    return $document;
  }

  /**
   * Save file content to disk.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   * @param string $content
   *   Full content of the file.
   * @param \Drupal\user\Entity\User $provider
   *   Provider.
   * @param bool $new_revision
   *   Create a new revision.
   */
  protected function saveFileToDisk(File &$file, $content, User $provider, $new_revision) {
    $is_new = TRUE;

    // Extract path from file.
    $destination = pathinfo($file->getFileUri(), PATHINFO_DIRNAME);
    $this->fileSystem->prepareDirectory($destination, $this->fileSystem::CREATE_DIRECTORY);

    // If file exists, copy existing and overwrite original.
    if ($new_revision && file_exists($file->getFileUri())) {
      $is_new = FALSE;

      // Create new file entity.
      /** @var \Drupal\file\Entity\File $new_file */
      $new_file = file_copy($file, $file->getFileUri(), $this->fileSystem::EXISTS_RENAME);
      if (!$new_file) {
        throw new BadRequestHttpException('Unable to write file' . $file->getFileUri());
      }

      // Detect mime type.
      if ($new_file->getMimeType() == 'undefined') {
        $new_file->setMimeType($this->mimeTypeGuesser->guess($new_file->getFileUri()));

        // Save file.
        $new_file->save();
      }

      // Swap filenames.
      $swap_uri = $file->getFileUri();
      $file->setFileUri($new_file->getFileUri());
      $new_file->setFileUri($swap_uri);

      // Save both.
      $file->save();
      $new_file->save();

      // Update media referencing this file.
      $usage_list = $this->fileUsage->listUsage($file);
      $usage_list = isset($usage_list['file']) ? $usage_list['file'] : [];
      $usage_list = isset($usage_list['media']) ? $usage_list['media'] : [];
      $usage_list = array_keys($usage_list);

      foreach ($usage_list as $media_id) {
        /** @var \Drupal\media\Entity\Media $media_entity */
        $media_entity = $this->entityTypeManager->getStorage('media')->load($media_id);

        // Move old file to revisions.
        $media_entity->field_media_file_revisions[] = [
          'target_id' => $file->id(),
        ];

        // Add new file.
        $media_entity->field_media_file = [
          'target_id' => $new_file->id(),
        ];

        // Force new revision and save.
        $media_entity->setNewRevision();
        $media_entity->save();
      }

      // Swap files so it gets the new content.
      $file = $new_file;
    }

    if ($uri = $this->fileSystem->saveData($content, $file->getFileUri(), $is_new ? $this->fileSystem::EXISTS_RENAME : $this->fileSystem::EXISTS_REPLACE)) {
      $file->setFileUri($uri);
      $file->setPermanent();

      // Detect mime type.
      if ($file->getMimeType() == 'undefined') {
        $file->setMimeType($this->mimeTypeGuesser->guess($uri));
      }

      // Save file.
      $file->save();

      // Create media if it's a new file.
      if ($is_new) {
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
    }
    else {
      throw new BadRequestHttpException('Unable to write file');
    }

    return $media_entity;
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

  /**
   * Move file to private file system.
   */
  protected function moveFileToPrivate($file) {
    $uri = $file->getFileUri();
    $new_uri = $uri;

    if (strpos($uri, 'private://') === FALSE) {
      $new_uri = str_replace('public://', 'private://', $uri);
    }

    // Make sure we need to move.
    if ($uri === $new_uri) {
      return;
    }

    // Make sure directory exists.
    $destination = pathinfo($new_uri, PATHINFO_DIRNAME);
    $this->fileSystem->prepareDirectory($destination, $this->fileSystem::CREATE_DIRECTORY);

    // Move file and update record.
    if (!file_move($file, $new_uri)) {
      throw new BadRequestHttpException('File could not be moved');
    }
  }

  /**
   * Move file to public file system.
   */
  protected function moveFileToPublic($file) {
    $uri = $file->getFileUri();
    $new_uri = $uri;

    if (strpos($uri, 'public://') === FALSE) {
      $new_uri = str_replace('private://', 'public://', $uri);
    }

    // Make sure we need to move.
    if ($uri === $new_uri) {
      return;
    }

    // Make sure directory exists.
    $destination = pathinfo($new_uri, PATHINFO_DIRNAME);
    $this->fileSystem->prepareDirectory($destination, $this->fileSystem::CREATE_DIRECTORY);

    // Move file and update record.
    if (!file_move($file, $new_uri)) {
      throw new BadRequestHttpException('File could not be moved');
    }
  }

}
