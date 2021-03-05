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
use Drupal\entity_usage\EntityUsage;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for media and file endpoints.
 */
class MediaController extends ControllerBase {

  use Filetrait;
  use MetadataTrait;
  use ProviderTrait;
  use ResourceTrait;
  use RevisionableResourceTrait;

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
   * Get media.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of media resources.
   *
   * @todo index the media into solr and use a search query.
   * @todo load all files at once with loadMulitple instead of individually.
   * @todo check ownership for private files?
   */
  public function getAllMedia(Request $request) {
    $data = [];
    $cache = $this->createResponseCache()->addCacheTags(['media']);

    // Get pagination.
    list($offset, $limit) = $this->getRequestPagination($request);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // Get the storage for media entities.
    $storage = $this->entityTypeManager->getStorage('media');

    // @todo handle limit and offset parameters.
    $ids = $storage->getQuery()->range($offset, $limit)->execute();

    /** @var \Drupal\media\Entity\Media[] $entities */
    $entities = $storage->loadMultiple($ids);

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($entities as $media) {
      $cache->addCacheableDependency($media);

      // For backward compatibility, this is not checking the
      // ownership of the private files. They will be marked as private and
      // with their uri hidden when processed by prepareMediaEntityData().
      //
      // @todo improve by extracting all the file ids and loading all the file
      // entities at once.
      try {
        /** @var \Drupal\file\Entity\File $file */
        $file = $this->loadMediaFile($media, $provider, FALSE);
      }
      // Skip if the file was not found.
      catch (NotFoundHttpException $exception) {
        continue;
      }

      $data[] = $this->prepareMediaEntityData($media, $file, $provider);
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get media.
   *
   * @param string $id
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of resources.
   */
  public function getMedia($id, Request $request) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($id);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($media, $provider);

    // Get the list of revisions for the media.
    $revisions = $this->getResourceEntityRevisionList('media', $media->id());

    $data = $this->prepareMediaEntityData($media, $file, $provider, $revisions);

    $cache = $this->createResponseCache()->addCacheableDependency($media);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get media revision.
   *
   * @param string $id
   *   Media uuid.
   * @param string $vid
   *   Revision id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of resources.
   */
  public function getMediaRevision($id, $vid, Request $request) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    /** @var \Drupal\media\Entity\MediaType $media_type */
    $media_type = $this->loadMediaType('file');

    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->loadResourceEntityRevision($id, $vid, 'media', $media_type, $provider);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($revision, $provider);

    $data = $this->prepareMediaEntityData($revision, $file, $provider);

    $cache = $this->createResponseCache()->addCacheableDependency($revision);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get media content.
   *
   * @param string $id
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Binary response.
   */
  public function getMediaContent($id, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($media, $provider);

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($media->getName()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Get media revision content.
   *
   * @param string $id
   *   Media uuid.
   * @param string $vid
   *   Revision id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Binary response.
   */
  public function getMediaRevisionContent($id, $vid, Request $request) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    /** @var \Drupal\media\Entity\MediaType $media_type */
    $media_type = $this->loadMediaType('file');

    /** @var \Drupal\media\Entity\Media $revision */
    $revision = $this->loadResourceEntityRevision($id, $vid, 'media', $media_type, $provider);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($revision, $provider);

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($revision->getName()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Get files.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of file resources.
   *
   * @todo index the files into solr and use a search query.
   */
  public function getFiles(Request $request) {
    $data = [];
    $cache = $this->createResponseCache()->addCacheTags(['files']);

    // Get pagination.
    list($offset, $limit) = $this->getRequestPagination($request);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // Get the storage for file entities.
    $storage = $this->entityTypeManager->getStorage('file');

    // @todo handle limit and offset parameters.
    $ids = $storage
      ->getQuery()
      // Skip the default generic media icon...
      // An entry for that file gets generated when a new media is created
      // if it doesn't exists...
      ->condition('uri', 'public://media-icons/generic/generic.png', '<>')
      ->range($offset, $limit)
      ->execute();

    /** @var \Drupal\file\Entity\File[] $files */
    $files = $storage->loadMultiple($ids);

    /** @var \Drupal\file\Entity\File $file */
    foreach ($files as $file) {
      /** @var \Drupal\media\Entity\Media $media */
      $media = $this->loadFileMedia($file);

      $data[] = $this->prepareFileEntityData($file, $media, $provider);

      $cache->addCacheableDependency($file);
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create file.
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

    if (!isset($params['mimetype'])) {
      $params['mimetype'] = 'undefined';
    }

    // @todo Remove as it's not used anywhere.
    if (!isset($params['alt'])) {
      $params['alt'] = $params['filename'];
    }

    // Support private files.
    $private = !empty($params['private']);

    // Create the file entity.
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->createFileEntity($params['filename'], $params['mimetype'], $private, $provider);

    // Media entity wrapping the file entity and created when the file's content
    // is saved.
    $media = NULL;

    // Case 1: binary content provided in the request params.
    if (isset($params['data'])) {
      // Decode data.
      $content = base64_decode($params['data']);
      $media = $this->saveFileToDisk($file, $content, $provider, FALSE);
    }
    // Case 2: file's content comes from a file in the dropfolder.
    elseif (!empty($params['use_dropfolder'])) {
      if (empty($provider->get('dropfolder')->value)) {
        throw new BadRequestHttpException('Dropfolder is not enabled');
      }

      $files = $this->fileSystem->scanDirectory($provider->get('dropfolder')->value, '/^' . $params['filename'] . '$/');
      if (!empty($files)) {
        // Only get the content of the first file.
        $content = file_get_contents(array_key_first($files));
        $media = $this->saveFileToDisk($file, $content, $provider, FALSE);
      }
      else {
        throw new BadRequestHttpException('File not found in dropfolder');
      }
    }
    // Case 3: file's content is fetch remotely.
    elseif (!empty($params['uri'])) {
      $content = $this->fetchRemoteFile($params['uri']);
      $media = $this->saveFileToDisk($file, $content, $provider, FALSE);
    }
    // Otherwise we save the file without content.
    else {
      $file->save();
    }

    // Invalidate file cache.
    Cache::invalidateTags(['files']);

    $data = [
      'message' => 'File created',
    ] + $this->prepareFileEntityData($file, $media, $provider);

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Get files.
   *
   * @param string $id
   *   File id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of file resources.
   */
  public function getFile($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadFile($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // If the file is private check if the provider is its owner.
    if ($this->fileIsPrivate($file)) {
      $this->providerIsOwner($file, $provider);
    }

    // Try to load the media associated with the file.
    $media = $this->loadFileMedia($file);

    $data = $this->prepareFileEntityData($file, $media, $provider);

    $cache = $this->createResponseCache()->addCacheableDependency($file);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Update file.
   *
   * @param string $id
   *   File id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function updateFile($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadFile($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // A file can only be updated by its owner.
    $this->providerIsOwner($file, $provider);

    // Update the file name.
    if (!empty($params['filename'])) {
      $new_filename = $params['filename'];
      $old_filename = $file->getFilename();
      // Only change the filename if it's different.
      if ($new_filename !== $old_filename) {
        $new_extension = pathinfo($new_filename, PATHINFO_EXTENSION);
        $old_extension = pathinfo($old_filename, PATHINFO_EXTENSION);
        // Disallow changing the extension.
        if ($new_extension !== $old_extension) {
          throw new BadRequestHttpException('The file extension cannot be changed');
        }
        // Set the new filename.
        $file->setFilename($new_filename);
        $file->save();
      }
    }

    // See if we need to move the file.
    if (isset($params['private'])) {
      $private_state = $this->fileIsPrivate($file);
      $private = !empty($params['private']);

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

    // Invalidate file cache.
    Cache::invalidateTags(['files']);

    $data = [
      'message' => 'File updated',
      'uuid' => $file->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete file.
   *
   * @param string $id
   *   File id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function deleteFile($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadFile($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // A file can only be deleted by its owner.
    $this->providerIsOwner($file, $provider);

    // @todo review that because once it has content a file is always
    // associated with a media so usage list will never be empty, meaning
    // a file cannot never be deleted?
    $usage_list = $this->fileUsage->listUsage($file);
    $usage_list = isset($usage_list['file']) ? $usage_list['file'] : [];
    $usage_count = count($usage_list);
    if ($usage_count > 0) {
      throw new BadRequestHttpException(strtr('File is still in use in @num places', [
        '@num' => $usage_count,
      ]));
    }

    $file->delete();

    // Invalidate file cache.
    Cache::invalidateTags(['files']);

    $data = [
      'message' => 'File is deleted',
      'uuid' => $file->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get file usage.
   *
   * @param string $id
   *   File id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of file resources.
   *
   * @todo review logic: do we want to return number of documents referencing
   * the file? Shouldn't we add some limit also
   */
  public function getFileUsage($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadFile($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // If the file is private check if the provider is its owner.
    if ($this->fileIsPrivate($file)) {
      $this->providerIsOwner($file, $provider);
    }

    $data = [];
    $cache = $this->createResponseCache()->addCacheableDependency($file);

    $usage_list = $this->fileUsage->listUsage($file);
    $usage_list = isset($usage_list['file']) ? $usage_list['file'] : [];
    foreach ($usage_list as $entity_type_id => $entity_ids) {
      $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple(array_keys($entity_ids));

      foreach ($entities as $entity) {
        $data[] = '/api/media/' . $entity->uuid();
        $cache->addCacheableDependency($entity);
      }
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get file content.
   *
   * @param string $id
   *   File id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Binary file response.
   */
  public function getFileContent($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadFile($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // If the file is private check if the provider is its owner.
    if ($this->fileIsPrivate($file)) {
      $this->providerIsOwner($file, $provider);
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
   *
   * @param string $id
   *   File id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   *
   * @todo If the file already has content, this should probably throw an error
   * and maybe indicate to use a PUT request to update the content or a POST
   * request on the media endpoint.
   * @todo return the media uuid?
   */
  public function createFileContent($id, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadFile($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // A file can only be deleted by its owner.
    $this->providerIsOwner($file, $provider);

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
   *
   * @param string $id
   *   File id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   *
   * @todo differentiate from createFileContent or remove.
   */
  public function updateFileContent($id, Request $request) {
    return $this->createFileContent($id, $request);
  }

  /**
   * Load a media type.
   *
   * @param string $id
   *   Node type uuid or machine_name.
   *
   * @return \Drupal\media\Entity\MediaType
   *   Node type entity.
   */
  protected function loadMediaType($id) {
    /** @var \Drupal\media\Entity\MediaType */
    return $this->loadResourceEntity('media_type', $id);
  }

}
