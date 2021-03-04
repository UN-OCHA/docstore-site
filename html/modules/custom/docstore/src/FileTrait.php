<?php

namespace Drupal\docstore;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * File related functions.
 */
trait FileTrait {

  use ProviderTrait;

  /**
   * Create a file entity with the given filename, mimetype and private state.
   *
   * @param string $filename
   *   File name.
   * @param string $mimetype
   *   File mime type.
   * @param bool $private
   *   Whether the file is private or not.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return \Drupal\file\Entity\File
   *   Media referencing the file.
   */
  public function createFileEntity($filename, $mimetype, $private, UserInterface $provider) {
    $hash = md5($filename);

    // Create URI.
    $destination = $this->config('system.file')->get('default_scheme') . '://';
    if ($private) {
      $destination = 'private://';
    }

    $destination .= 'files/';
    $destination .= substr($hash, 0, 3);
    $destination .= '/' . substr($hash, 3, 3);
    $destination .= '/' . $filename;

    // Store files in sub directories.
    $file = File::create();
    $file->setOwnerId($provider->id());
    $file->setMimeType($mimetype);
    $file->setFileName($filename);
    $file->setFileUri($destination);
    $file->setTemporary();

    return $file;
  }

  /**
   * Fetch a remote file.
   *
   * @param string $uri
   *   Remote file uri.
   *
   * @return string
   *   File's content.
   *
   * @todo add some validation.
   * @todo catch errors and throw proper exception.
   */
  public function fetchRemoteFile($uri) {
    try {
      $content = file_get_contents($uri);
    }
    catch (\Exception $exception) {
      $content = '';
    }
    if (empty($content)) {
      throw new BadRequestHttpException(strtr('Unable to fetch file with uri @uri', [
        '@uri' => $uri,
      ]));
    }
    return $content;
  }

  /**
   * Fetch and create a file.
   *
   * @param string $uri
   *   File uri.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return \Drupal\media\Entity\Media
   *   Media referencing the file.
   */
  public function fetchAndCreateFile($uri, UserInterface $provider) {
    // Fetch the file content.
    $content = $this->fetchRemoteFile($uri);

    // Create the file entity.
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->createFileEntity(basename($uri), 'undefined', FALSE, $provider);

    // Save the content to disk.
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->saveFileToDisk($file, $content, $provider, FALSE);

    return $media;
  }

  /**
   * Save file content to disk.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   * @param string $content
   *   Full content of the file.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $create_revision
   *   Create a new revision.
   *
   * @return \Drupal\media\Entity\Media
   *   Media referencing the file.
   */
  public function saveFileToDisk(File &$file, $content, UserInterface $provider, $create_revision) {
    $file_uri = $file->getFileUri();
    $new_revision = FALSE;
    $log_message = 'File content updated';

    // Create the directory for the file.
    $directory = pathinfo($file_uri, PATHINFO_DIRNAME);
    if (!$this->fileSystem->prepareDirectory($directory, $this->fileSystem::CREATE_DIRECTORY)) {
      throw new HttpException(500, 'Unable to create directory');
    }

    // Load the media for the file.
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadFileMedia($file);

    // If the file is temporary, we assume it never had content and we save
    // the content without overwriting any other files with the same name.
    if ($file->isTemporary()) {
      // Save the content, potentially creating a new uri.
      $new_file_uri = $this->writeFileContent($content, $file_uri, FALSE);

      // Update the file uri as it may have changed.
      $file->setFileUri($new_file_uri);
    }
    // Otherwise if not ask to create a revision or the file doesn't exists,
    // we overwrite/create the file on disk.
    elseif (empty($create_revision) || !file_exists($file_uri)) {
      // Replace the file content.
      $this->writeFileContent($content, $file_uri, TRUE);
    }
    // In case of a new revision, create a new file that will point at the old
    // uri updated with the new content and have the old file point at a new
    // uri with the old content.
    // This ensures links to the existing file uri will have the new content.
    else {
      // Copy the existing file.
      /** @var \Drupal\file\Entity\File $file */
      $new_file = file_copy($file, $file_uri);
      if (empty($new_file)) {
        throw new HttpException(500, 'Unable to write file');
      }

      // Make the old file point at the new uri with the old content and save.
      $file->setFileUri($new_file->getFileUri());
      $file->save();

      // Replace the content of the old file uri with the new content.
      $this->writeFileContent($content, $file_uri, TRUE);

      // Make the new file point at the old uri with the new content.
      $new_file->setFileUri($file_uri);

      // Swap the files so the rest of the processing applies to the new file
      // and the calling function gets the new file as well.
      $file = $new_file;

      // Indicate we want to create a new revision.
      $new_revision = TRUE;
    }

    // Ensure the mimetype is correct, the file permanent and save.
    $this->ensureCorrectFileMimeType($file);
    $file->setPermanent();
    $file->save();

    // Create a new media if there is none.
    /** @var \Drupal\media\Entity\Media $media */
    if (empty($media)) {
      $media = Media::create([
        'bundle' => 'file',
        'uid' => $provider->id(),
        'name' => $file->getFilename(),
        'status' => TRUE,
      ]);
      $new_revision = TRUE;
      $log_message = 'File content created';
    }

    // Have the media point at the correct file.
    // @phpstan-ignore-next-line
    $media->field_media_file->target_id = $file->id();

    // Create a new revision.
    if ($new_revision) {
      $media->setRevisionCreationTime(time());
      $media->setRevisionLogMessage($log_message);
      $media->setRevisionUserId($provider->id());
      $media->setNewRevision();
      $media->isDefaultRevision(TRUE);
    }

    // Update the media.
    $media->save();

    // Invalidate cache as a new file and media were created/updated.
    Cache::invalidateTags(['media', 'files']);

    return $media;
  }

  /**
   * Write some file content to disk.
   *
   * @param string $content
   *   File content.
   * @param string $uri
   *   File uri.
   * @param bool $replace
   *   Whether to overwrite the file or create a new file.
   *
   * @return string
   *   URI of the file that was written. It's equal to the given uri if
   *   replace was TRUE otherwise, it may be different with a number appended
   *   to the filemame if there was already an existing file with the same name.
   */
  public function writeFileContent($content, $uri, $replace = FALSE) {
    $behavior = $replace ? $this->fileSystem::EXISTS_REPLACE : $this->fileSystem::EXISTS_RENAME;

    try {
      $uri = $this->fileSystem->saveData($content, $uri, $behavior);
    }
    catch (\Exception $exception) {
      throw new HttpException(500, 'Unable to write file');
    }
    return $uri;
  }

  /**
   * Update the mimetype of a file if it's undefined.
   *
   * Note: this doesn't save the file.
   *
   * @param \Drupal\file\Entity\File $file
   *   File to update.
   */
  public function ensureCorrectFileMimeType(File $file) {
    // Ensure the file has the correct mimetype.
    if ($file->getMimeType() == 'undefined') {
      $file->setMimeType($this->mimeTypeGuesser->guess($file->getFileUri()));
    }
  }

  /**
   * Move file to private file system.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   */
  public function moveFileToPrivate(File $file) {
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
      throw new HttpException(500, 'File could not be moved');
    }

    // Invalidate cache as the file URI changed.
    Cache::invalidateTags(['media', 'files']);
  }

  /**
   * Move file to public file system.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   */
  public function moveFileToPublic(File $file) {
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
      throw new HttpException(500, 'File could not be moved');
    }

    // Invalidate cache as the file URI changed.
    Cache::invalidateTags(['media', 'files']);
  }

  /**
   * Get the media referencing the given file.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   *
   * @return \Drupal\media\Entity\Media|null
   *   Media entity if found or NULL.
   */
  public function loadFileMedia(File $file) {
    $usage_list = $this->fileUsage->listUsage($file);
    $usage_list = isset($usage_list['file']) ? $usage_list['file'] : [];
    $usage_list = isset($usage_list['media']) ? $usage_list['media'] : [];

    if (empty($usage_list)) {
      return NULL;
    }

    /** @var \Drupal\media\Entity\Media */
    return $this->entityTypeManager
      ->getStorage('media')
      ->load(array_key_first($usage_list));
  }

  /**
   * Load a media entity.
   *
   * @param string $id
   *   Media id or uuid.
   *
   * @return \Drupal\media\Entity\Media
   *   Media entity.
   */
  public function loadMedia($id) {
    /** @var \Drupal\media\Entity\Media */
    return $this->loadResourceEntity('media', $id);
  }

  /**
   * Load a file entity.
   *
   * @param string $id
   *   File id or uuid.
   *
   * @return \Drupal\file\Entity\File
   *   File entity.
   */
  public function loadFile($id) {
    /** @var \Drupal\file\Entity\File */
    return $this->loadResourceEntity('file', $id);
  }

  /**
   * Load a file referenced by a media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\user\UserInterface|null $provider
   *   Provider.
   * @param bool $check_ownership
   *   If TRUE and the media file is private, check if the provider is the
   *   owner of the file.
   *
   * @return \Drupal\file\Entity\File
   *   File referenced by the media.
   *
   * @throws \Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException
   *   403 Access Denied if the provider doesn't have access to the file.
   *
   * @throws \Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException
   *   404 Not Found if the file couldn't be loaded.
   */
  public function loadMediaFile(Media $media, ?UserInterface $provider = NULL, $check_ownership = TRUE) {
    $cache = $this->createResponseCache()->addCacheableDependency($media);
    $file_id = $media->getSource()->getSourceFieldValue($media);

    if (empty($file_id)) {
      throw new CacheableNotFoundHttpException($cache, 'Media file not found');
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($file_id);

    if (empty($file)) {
      throw new CacheableNotFoundHttpException($cache, 'Media file not found');
    }

    // If the file is private check if the provider is its owner.
    if ($check_ownership && $this->fileIsPrivate($file)) {
      try {
        $this->providerIsOwner($file, $provider);
      }
      catch (AccessDeniedHttpException $exception) {
        throw new CacheableAccessDeniedHttpException($cache, $exception->getMessage());
      }
    }

    return $file;
  }

}
