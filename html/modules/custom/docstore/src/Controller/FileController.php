<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\Unicode;
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
use Drupal\docstore\FileTrait;
use Drupal\docstore\MetadataTrait;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\docstore\RevisionableResourceTrait;
use Drupal\docstore\UtilityTrait;
use Drupal\entity_usage\EntityUsage;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the file endpoints.
 */
class FileController extends ControllerBase {

  use Filetrait;
  use MetadataTrait;
  use ProviderTrait;
  use ResourceTrait;
  use RevisionableResourceTrait;
  use UtilityTrait;

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
   * Get files.
   *
   * GET /api/v1/files.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of file resources.
   *
   * @todo index the media into solr and use a search query.
   */
  public function getFiles(Request $request) {
    $data = [];
    $cache = $this->createResponseCache()
      ->addCacheTags(['files']);

    // Get pagination.
    list($offset, $limit) = $this->getRequestPagination($request);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // Get the storage for file entities.
    $storage = $this->entityTypeManager->getStorage('media');

    // Get the list of media ids for the given range.
    $ids = $storage
      ->getQuery()
      ->range($offset, $limit)
      ->execute();

    /** @var \Drupal\media\Entity\Media[] $media_entities */
    $media_entities = $storage->loadMultiple($ids);

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($media_entities as $media) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $this->loadMediaFile($media);

      // Prepare the media information without the revisions.
      $data[] = $this->prepareMediaEntityData($media, $file, $provider);

      $cache->addCacheableDependency($media);
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create a file.
   *
   * POST /api/v1/files.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function createFile(Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Filename is required.
    if (!isset($params['filename'])) {
      throw new BadRequestHttpException('File name is required');
    }

    // Disallow file name without an extension.
    $extension = pathinfo($params['filename'], PATHINFO_EXTENSION);
    if (empty($extension)) {
      throw new BadRequestHttpException('A valid file extension is required');
    }

    if (empty($params['mimetype'])) {
      $params['mimetype'] = 'undefined';
    }

    // Support private files.
    $private = !empty($params['private']);

    // Create the file entity.
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->createFileEntity($params['filename'], $params['mimetype'], $private, $provider);

    // Case 1: binary content provided in the request params.
    if (!empty($params['data'])) {
      $content = base64_decode($params['data']);
    }
    // Case 2: file's content comes from a file in the dropfolder.
    elseif (!empty($params['use_dropfolder'])) {
      $content = $this->fetchDropfolderFileContent($params['filename'], $provider);
    }
    // Case 3: file's content is fetch remotely.
    elseif (!empty($params['uri'])) {
      $content = $this->fetchRemoteFileContent($params['uri']);
    }

    // Save the file's content if provided.
    if (!empty($content)) {
      $file = $this->saveFileToDisk($file, $content, $provider);
    }
    // Otherwise we save the file without content.
    else {
      $file->save();
    }

    // Create the media entity.
    $media = $this->createMediaEntity($file, $private, $provider);

    // Save the media and generate the symlinks if possible.
    $this->saveMedia($media, $file, $provider);

    // Get the media data without the list of revisions as this is the first.
    $data = [
      'message' => 'File created',
    ] + $this->prepareMediaEntityData($media, $file, $provider);

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Get a file (media).
   *
   * GET /api/v1/files/{uuid}.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the file info.
   */
  public function getFile($uuid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    // Get the provider - can be anonymous.
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // If the media is private check if the provider is its owner.
    if ($this->mediaIsPrivate($media)) {
      $this->providerIsOwner($media, $provider);
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($media);

    // @todo review the field list, notably to add the file uuids?
    $revisions = $this->getResourceEntityRevisionList('media', $media->id());

    // Get the media data with the list of its revisions.
    $data = $this->prepareMediaEntityData($media, $file, $provider, $revisions);

    $cache = $this->createResponseCache()
      ->addCacheTags(['files'])
      ->addCacheableDependency($media);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Update file properties: filename, private.
   *
   * PATCH /api/v1/files/{uuid}.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function updateFile($uuid, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // A file can only be updated by its owner.
    //
    // @todo maybe allow changing the filename for other provider with edit
    // access to this file?
    $this->providerIsOwner($media, $provider);

    $updated = FALSE;

    // Update the file name.
    if (!empty($params['filename'])) {
      $this->renameMedia($media, $params['filename']);
      $updated = TRUE;
    }

    // See if we need to move the file.
    if (isset($params['private'])) {
      $private = !empty($params['private']);

      // Only move the file if it's private status should changed.
      if ($private !== $this->mediaIsPrivate($media)) {
        $this->moveMediaFiles($media, $private);
        $updated = TRUE;
      }
    }

    // Invalidate file cache.
    if ($updated) {
      Cache::invalidateTags(['files']);
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($media);

    // @todo review the field list, notably to add the file uuids?
    $revisions = $this->getResourceEntityRevisionList('media', $media->id());

    $data = [
      'message' => 'File updated',
    ] + $this->prepareMediaEntityData($media, $file, $provider, $revisions);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete a file and all it's revisions.
   *
   * DELETE /api/v1/files/{uuid}.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function deleteFile($uuid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Only the owner of the media can fully delete.
    $this->providerIsOwner($media, $provider);

    // Check if the media is in use.
    $usage_count = count($this->entityUsage->listSources($media));
    if (!empty($usage)) {
      throw new BadRequestHttpException(strtr('File is still in use in @num places', [
        '@num' => $usage_count,
      ]));
    }

    // Delete the symlinks for the media.
    $this->deleteMediaSymlinks($media);

    // Delete all the files associated with the media revisions.
    foreach ($this->loadResourceRevisions($media) as $revision) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $this->loadMediaFile($revision);
      $file->delete();
    }

    // Delete the media.
    $media->delete();

    // Invalidate file cache.
    Cache::invalidateTags(['files']);

    $data = [
      'message' => 'File deleted',
      'uuid' => $media->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Select a version of the media as active for the provider.
   *
   * PUT /api/v1/files/{uuid}/select.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function selectFile($uuid, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // If the file is private check if the provider is its owner.
    if ($this->mediaIsPrivate($media)) {
      $this->providerIsOwner($media, $provider);
    }

    if (!isset($params['target'])) {
      throw new BadRequestHttpException('Target parameter is required');
    }

    // Retrieve the list of provider selected versions.
    $selection = $this->getMediaSelectedFileVersions($media);

    // An empty target hides the media for the provider.
    if (empty($params['target']) || $params['target'] === 'hidden') {
      $this->hideMediaForProvider($media, $provider);
      $selection[$provider->uuid()] = 'hidden';
    }
    // If the target is the latest revision, remove the link specific to the
    // provider and remove the file target information for the provider.
    elseif ($params['target'] === 'latest') {
      $this->unselectMediaRevisionForProvider($media, $provider);
      unset($selection[$provider->uuid()]);
    }
    // Otherwise, create a link to the specific revision.
    else {
      $target = $this->selectMediaRevisionForProvider($media, $params['target'], $provider);
      $selection[$provider->uuid()] = $target;
    }

    // Update the list of selected file versions per provider.
    $this->setMediaSelectedFileVersions($media, $selection);
    $media->save();

    $data = [
      'message' => 'File version selected',
      'uuid' => $media->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get file usage.
   *
   * GET /api/v1/files/{uuid}/usage.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of resources referencing the media.
   */
  public function getFileUsage($uuid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // If the media is private check if the provider is its owner.
    if ($this->mediaIsPrivate($media)) {
      $this->providerIsOwner($media, $provider);
    }

    $data = [];
    $cache = $this->createResponseCache()
      ->addCacheTags(['files'])
      ->addCacheableDependency($media);

    // Generate a list of resource endpoints for the entities referencing
    // the media.
    $usage_list = $this->entityUsage->listUsage($media);
    foreach ($usage_list as $entity_type_id => $entity_ids) {
      $resource_type = $this->getResourceType($entity_type_id);

      $entities = $this->entityTypeManager
        ->getStorage($entity_type_id)
        ->loadMultiple(array_keys($entity_ids));

      foreach ($entities as $entity) {
        $data[] = '/api/' . $resource_type . '/' . $entity->bundle() . '/' . $entity->uuid();
        $cache->addCacheableDependency($entity);
      }
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get file content.
   *
   * GET /api/v1/files/{uuid}/content.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Binary file response.
   */
  public function getFileContent($uuid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // If the media is private check if the provider is its owner.
    if ($this->mediaIsPrivate($media)) {
      $this->providerIsOwner($media, $provider);
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($media);

    // Ensure there is content.
    if (!file_exists($file->getFileUri())) {
      throw new NotFoundHttpException('The file has not content');
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($media->getName()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Create file content.
   *
   * POST /api/v1/files/{uuid}/content.
   *
   * This creates a new revision if the file attached to the media already
   * had content.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function createFileContent($uuid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // If the media is private check if the provider is its owner.
    if ($this->mediaIsPrivate($media)) {
      $this->providerIsOwner($media, $provider);
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($media);

    // @todo add some validation.
    $file = $this->saveFileToDisk($file, $request->getContent(), $provider);

    // Update the media and regenerate the symlinks if necessary.
    $this->saveMedia($media, $file, $provider);

    $data = [
      'message' => 'File content created',
    ] + $this->prepareMediaEntityData($media, $file, $provider);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get file revisions.
   *
   * GET /api/v1/files/{uuid}/revisions.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of revisions for the media.
   */
  public function getFileRevisions($uuid, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // If the media is private check if the provider is its owner.
    if ($this->mediaIsPrivate($media)) {
      $this->providerIsOwner($media, $provider);
    }

    $cache = $this->createResponseCache()
      ->addCacheTags(['files'])
      ->addCacheableDependency($media);

    // @todo review the field list, notably to add the file uuids?
    $data = $this->getResourceEntityRevisionList('media', $media->id());

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get a revision.
   *
   * GET /api/v1/files/{uuid}/revisions/{revision_id}.
   *
   * @param string $uuid
   *   Media uuid.
   * @param string $revision_id
   *   Revision id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of revisions for the media.
   */
  public function getFileRevision($uuid, $revision_id, Request $request) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->loadMediaRevision($uuid, $revision_id);
    if (empty($revision)) {
      throw new NotFoundHttpException('Revision not found');
    }

    // If the media is private check if the provider is its owner.
    if ($this->mediaIsPrivate($revision)) {
      $this->providerIsOwner($revision, $provider);
    }

    $cache = $this->createResponseCache()
      ->addCacheTags(['files'])
      ->addCacheableDependency($revision);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($revision);

    // Prepare the media information without the revisions.
    $data = $this->prepareMediaEntityData($revision, $file, $provider);

    // Indicate that this is the latest revision.
    if ($revision->isDefaultRevision()) {
      $data['latest'] = TRUE;
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Delete a revision.
   *
   * DELETE /api/v1/files/{uuid}/revisions/{revision_id}.
   *
   * Logic:
   * - If the revision is in use by a provider who is not the owner
   *   -> deny
   * - If the revision is the latest
   *   -> attempt to revert to the previous version
   *   -> if not previous version, attempt to delete the media
   * - If the revision can be delete
   *   -> remove its file, delete it and regenerate the symlinks.
   *
   * @param string $uuid
   *   Media uuid.
   * @param string $revision_id
   *   Revision id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   *
   * @todo allow removal of a revision by the owner regardless of whether it's
   * in use by another provider or not?
   */
  public function deleteFileRevision($uuid, $revision_id, Request $request) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->loadMediaRevision($uuid, $revision_id);
    if (empty($revision)) {
      throw new NotFoundHttpException('Revision not found');
    }

    // If the media is private check if the provider is its owner.
    if ($this->mediaIsPrivate($revision)) {
      $this->providerIsOwner($revision, $provider);
    }

    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($uuid);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($revision);

    // Retrieve the list of provider selected versions.
    $selection = $this->getMediaSelectedFileVersions($media);

    // Check if the revision is in use by another provider.
    foreach ($selection as $provider_uuid => $target) {
      if ($target === $file->uuid() && $provider_uuid !== $provider->uuid()) {
        // Deletion is forbidden if the current revision is in use by another
        // provider.
        throw new HttpException(403, 'The revision is in use by another provider');
      }
    }

    // If the revision is the latest, attempt to revert to the previous revision
    // before removing it.
    if ($revision->isDefaultRevision()) {
      /** @var \Drupal\media\Entity\Media $previous_revision */
      $previous_revision = $this->loadMediaRevision($uuid, $revision_id, TRUE);

      // If there is a previous revision, mark it as the latest revision.
      if (!empty($previous_revision)) {
        $previous_revision->isDefaultRevision(TRUE);
        $previous_revision->save();
      }
      // Otherwise, attempt to delete the media.
      else {
        return $this->deleteFile($uuid, $request);
      }
    }

    // Delete the file for this revision.
    $file->delete();

    // Mark the revision as non default and delete it.
    $revision->isDefaultRevision(FALSE);
    $revision->delete();

    // Load the media with in its latest revision and regenerate the symlinks.
    $this->regenerateMediaSymlinks($this->loadMedia($uuid));

    $data = [
      'message' => 'Revision deleted',
      'uuid' => $revision->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get revision content.
   *
   * GET /api/v1/files/{uuid}/revisions/{revision_id}/content.
   *
   * @param string $uuid
   *   Media uuid.
   * @param string $revision_id
   *   Revision id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Binary file response.
   */
  public function getFileRevisionContent($uuid, $revision_id, Request $request) {
    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->loadMediaRevision($uuid, $revision_id);
    if (empty($revision)) {
      throw new NotFoundHttpException('Revision not found');
    }

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // If the media is private check if the provider is its owner.
    if ($this->mediaIsPrivate($revision)) {
      $this->providerIsOwner($revision, $provider);
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($revision);

    // Ensure there is content.
    if (!file_exists($file->getFileUri())) {
      throw new NotFoundHttpException('The file has not content');
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($revision->getName()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

}
