<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\Cache;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\UserInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * File related functions.
 */
trait FileTrait {

  use ProviderTrait;
  use UtilityTrait;

  /**
   * Generate a file uri based on its uuid and filename.
   *
   * @param string $uuid
   *   File uuid.
   * @param string $extension
   *   File extension.
   * @param bool $private
   *   Whether the file is private or not.
   *
   * @return string
   *   File uri.
   */
  public function generateFileUri($uuid, $extension, $private) {
    $uri = $private ? 'private://' : 'public://';
    $uri .= 'files/';
    $uri .= substr($uuid, 0, 2);
    $uri .= '/' . substr($uuid, 2, 2);
    $uri .= '/' . $uuid . '.' . $extension;

    return $uri;
  }

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
   * @param string|null $uuid
   *   Optional media UUID to use to generate the file UUID.
   *
   * @return \Drupal\file\Entity\File
   *   Media referencing the file.
   */
  public function createFileEntity($filename, $mimetype, $private, UserInterface $provider, $uuid = NULL) {
    // Generate a new UUID based on the given uuid if defined.
    $uuid = $this->generateUuid($uuid);

    // Create URI.
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $uri = $this->generateFileUri($uuid, $extension, $private);

    // Store files in sub directories.
    $file = File::create(['uuid' => $uuid]);
    $file->setOwnerId($provider->id());
    $file->setMimeType($mimetype);
    $file->setFileName($filename);
    $file->setFileUri($uri);
    $file->setTemporary();

    return $file;
  }

  /**
   * Create a file entity with the given filename, mimetype and private state.
   *
   * @param \Drupal\file\Entity\File $file
   *   File associated with the media.
   * @param bool $private
   *   Whether the media is private or not.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string|null $uuid
   *   Optional UUID to use for the media.
   *
   * @return \Drupal\media\Entity\Media
   *   Media referencing the file.
   */
  public function createMediaEntity(File $file, $private, UserInterface $provider, $uuid = NULL) {
    // Use the provided UUID or generate an new one.
    $uuid = $uuid ?: static::generateUuid();

    $media = Media::create([
      'uuid' => $uuid,
      'bundle' => 'file',
      'uid' => $provider->id(),
      'name' => $file->getFilename(),
      'status' => TRUE,
      'field_media_file' => [
        'target_id' => $file->id(),
      ],
    ]);

    return $media;
  }

  /**
   * Save a media, updating the latest symlink.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\file\Entity\File $file
   *   File the media should reference. If it's different than the file the
   *   media is currently referencing, then create a new revision.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   */
  public function saveMedia(Media $media, File $file, UserInterface $provider) {
    // @phpstan-ignore-next-line
    $media_file_id = $media->field_media_file->target_id;

    // If the file is different, this is a new revision.
    // No strict equality as the ids can be numeric strings or ints.
    if ($file->id() != $media_file_id) {
      // @phpstan-ignore-next-line
      $media->field_media_file->target_id = $file->id();
      $new_revision = TRUE;
      $log_message = 'File updated';
    }
    else {
      $new_revision = FALSE;
      $log_message = 'File created';
    }

    // Create a new revision if needed.
    $media->setRevisionCreationTime(time());
    $media->setRevisionLogMessage($log_message);
    $media->setRevisionUserId($provider->id());
    $media->setNewRevision($new_revision);
    $media->isDefaultRevision(TRUE);

    $media->save();

    // Ensure the symlink to the file is created if the file exists on disk.
    if (!$file->isTemporary() && file_exists($file->getFileUri())) {
      $this->createMediaSymlink($media, NULL, $media->getOwner(), 'latest');
    }

    Cache::invalidateTags(['files']);
  }

  /**
   * Fetch the content of a remote file.
   *
   * @param string $uri
   *   Remote file uri.
   *
   * @return string
   *   File content.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the file's content couldn't be fetch.
   *
   * @todo review the settings.
   *
   * @todo adjust the exception based on the result of the request.
   *
   * @todo investigate refactoring this and fetchRemoteContentAndCreateFile() to
   * fetch the content asynchronously.
   */
  public function fetchRemoteFileContent($uri) {
    try {
      $config = $this->configFactory->get('docstore.settings');

      $options = [
        'timeout' => $config->get('remote_file_fetch.timeout') ?: 300,
        'connect_timeout' => $config->get('remote_file_fetch.connect_timeout') ?: 10,
      ];

      $content = \Drupal::httpClient()
        ->get($uri, $options)
        ->getBody()
        ->getContents();
    }
    catch (RequestException $exception) {
      throw new BadRequestHttpException(strtr('Failed to fetch file due to error "%error"', [
        '%error' => $exception->getMessage(),
      ]));
    }
    if ($content === '') {
      throw new BadRequestHttpException(strtr('The file at @uri was empty', [
        '@uri' => $uri,
      ]));
    }
    return $content;
  }

  /**
   * Get the content of a local file (ex: dropfolder).
   *
   * @param string $path
   *   Local file path.
   *
   * @return string
   *   File's content.
   *
   * @todo review exceptions.
   */
  public function fetchLocalFileContent($path) {
    $content = @file_get_contents($path);
    // Handle error while retrieving the content.
    if ($content === FALSE) {
      throw new BadRequestHttpException(strtr('Unable to retrieve file @path', [
        '@path' => $path,
      ]));
    }
    // Disallow files without content.
    elseif ($content === '') {
      throw new BadRequestHttpException(strtr('The file @path was empty', [
        '@path' => $path,
      ]));
    }
    return $content;
  }

  /**
   * Get the content of a file in the provider's dropfolder.
   *
   * @param string $filename
   *   Name of the file in the dropfolder.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return string
   *   File's content.
   *
   * @todo review exceptions.
   */
  public function fetchDropfolderFileContent($filename, UserInterface $provider) {
    if (empty($provider->get('dropfolder')->value)) {
      throw new BadRequestHttpException('Dropfolder is not enabled');
    }

    $files = $this->fileSystem->scanDirectory($provider->get('dropfolder')->value, '/^' . $filename . '$/');
    if (empty($files)) {
      throw new BadRequestHttpException('File not found in dropfolder: ' . $filename);
    }

    // Only get the content of the first file.
    return $this->fetchLocalFileContent(array_key_first($files));
  }

  /**
   * Fetch a remote file and create a file resource.
   *
   * @param string $uri
   *   File uri.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string|null $uuid
   *   Optional media uuid.
   *
   * @return \Drupal\media\Entity\Media
   *   Media referencing the file.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the file name from the uri doesn't have a valid
   *   extension.
   */
  public function fetchRemoteContentAndCreateFile($uri, UserInterface $provider, $uuid = NULL) {
    if (!empty($uuid) && !$this->validateEntityUuid('media', $uuid)) {
      throw new BadRequestHttpException('File UUID invalid or already in use');
    }

    $filename = basename($uri);

    // Disallow file name without an extension.
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (empty($extension)) {
      throw new BadRequestHttpException('A valid file extension is required');
    }

    // Fetch the file content.
    $content = $this->fetchRemoteFileContent($uri);

    // Create the file entity.
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->createFileEntity($filename, 'undefined', FALSE, $provider, $uuid);

    // Save the content to disk.
    /** @var \Drupal\media\Entity\Media $media */
    $file = $this->saveFileToDisk($file, $content, $provider);

    // Create media item.
    $media = $this->createMediaEntity($file, FALSE, $provider, $uuid);
    $this->saveMedia($media, $file, $provider);

    return $media;
  }

  /**
   * Fetch a dropfolder file and create a file resource.
   *
   * @param string $filename
   *   File name.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string|null $uuid
   *   Optional media uuid.
   *
   * @return \Drupal\media\Entity\Media
   *   Media referencing the file.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the file name from the uri doesn't have a valid
   *   extension.
   */
  public function fetchDropfolderContentAndCreateFile($filename, UserInterface $provider, $uuid = NULL) {
    if (!empty($uuid) && !$this->validateEntityUuid('media', $uuid)) {
      throw new BadRequestHttpException('File UUID invalid or already in use');
    }

    // Disallow file name without an extension.
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (empty($extension)) {
      throw new BadRequestHttpException('A valid file extension is required');
    }

    // Fetch the file content.
    $content = $this->fetchDropfolderFileContent($filename, $provider);

    // Create the file entity.
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->createFileEntity($filename, 'undefined', FALSE, $provider, $uuid);

    // Save the content to disk.
    /** @var \Drupal\media\Entity\Media $media */
    $file = $this->saveFileToDisk($file, $content, $provider);

    // Create media item.
    $media = $this->createMediaEntity($file, FALSE, $provider, $uuid);
    $this->saveMedia($media, $file, $provider);

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
   *
   * @return \Drupal\file\Entity\File
   *   The file with the content. It will be a new file, if the given one
   *   already had its content saved on disk.
   */
  public function saveFileToDisk(File $file, $content, UserInterface $provider) {
    $file_uri = $file->getFileUri();

    // If there is no existing file on disk for the file entity, we create
    // the content as the file's uri.
    if (!file_exists($file_uri)) {
      $this->writeFileContent($content, $file_uri);
    }
    // Otherwise, we create a new file.
    else {
      // Create a new file.
      $file = $this->createFileEntity($file->getFilename(), $file->getMimeType(), $this->fileIsPrivate($file), $provider);

      // Save the new content to the new uri.
      $this->writeFileContent($content, $file->getFileUri());
    }

    // Ensure the mimetype is correct, the file is permanent and save.
    $this->ensureCorrectFileMimeType($file);
    $file->setPermanent();
    $file->save();

    return $file;
  }

  /**
   * Prepare a directory for a uri.
   *
   * @param string $uri
   *   File uri.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function prepareDirectory($uri) {
    $directory = pathinfo($uri, PATHINFO_DIRNAME);
    if (!$this->fileSystem->prepareDirectory($directory, $this->fileSystem::CREATE_DIRECTORY)) {
      throw new HttpException(500, 'Unable to create directory');
    }
  }

  /**
   * Write some file content to disk.
   *
   * @param string $content
   *   File content.
   * @param string $uri
   *   File uri.
   *
   * @return string
   *   URI of the file that was written. It's equal to the given uri.
   */
  public function writeFileContent($content, $uri) {
    // Create the directory for the file.
    $this->prepareDirectory($uri);

    try {
      // We use `uuid` for file names so there shouldn't be collisions.
      $uri = $this->fileSystem->saveData($content, $uri, $this->fileSystem::EXISTS_ERROR);
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
   * Move media files for all the revisions.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param bool $private
   *   Whether to move all the files to the private file system or the public
   *   one.
   */
  public function moveMediaFiles(Media $media, $private) {
    // Move the file associated to each revision of the media.
    foreach ($this->loadResourceRevisions($media) as $revision) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $this->loadMediaFile($revision);

      // Move the file.
      $this->moveFile($file, $private);
    }
    $this->regenerateMediaSymlinks($media);
  }

  /**
   * Move a file to a the public or private file directory.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   * @param bool $private
   *   TRUE to move the file to the private directory, otherwise move it to
   *   the public location.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   500 Internal server error if the file couln't be moved.
   */
  public function moveFile(File $file, $private) {
    // Skip if the file is already in the proper place.
    if ($this->fileIsPrivate($file) === $private) {
      return;
    }

    $source = $file->getFileUri();

    if ($private) {
      $destination = str_replace('public://', 'private://', $source);
    }
    else {
      $destination = str_replace('private://', 'public://', $source);
    }

    // Create the directory if doesn't exist.
    $this->prepareDirectory($destination);

    // Move the file.
    $result = $this->fileSystem->move($source, $destination, $this->fileSystem::EXISTS_ERROR);
    if (empty($result)) {
      throw new HttpException(500, 'File could not be moved');
    }

    // Update the file uri and save.
    $file->setFileUri($destination);
    $file->save();
  }

  /**
   * Rename media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param string $new_name
   *   New filename.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the file name extension is different form the
   *   current one.
   */
  public function renameMedia(Media $media, $new_name) {
    $old_name = $media->getName();

    // Only change the name if it's different.
    if ($new_name !== $old_name) {
      $new_extension = pathinfo($new_name, PATHINFO_EXTENSION);
      $old_extension = pathinfo($old_name, PATHINFO_EXTENSION);
      // Disallow changing the extension.
      if ($new_extension !== $old_extension) {
        throw new BadRequestHttpException('The file extension cannot be changed');
      }
      // Set the new name.
      $media->setName($new_name);
      $media->save();
    }
  }

  /**
   * Hide the media for the given provider.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   */
  public function hideMediaForProvider(Media $media, UserInterface $provider) {
    // Delete any specific link for the provider.
    $this->removeMediaSymlink($media, $provider, 'provider');

    // Create a hidden symlink for the provider.
    $this->createMediaSymlink($media, NULL, $provider, 'provider-hidden');
  }

  /**
   * Remove any media link specific to the provider.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   */
  public function unselectMediaRevisionForProvider(Media $media, UserInterface $provider) {
    // Delete any specific link for the provider.
    $this->removeMediaSymlink($media, $provider, 'provider');

    // Delete any symlink hiding the media to the provider.
    $this->removeMediaSymlink($media, $provider, 'provider-hidden');
  }

  /**
   * Hide the media for the given provider.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param string $target
   *   File uuid or revision id.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return string
   *   The uuid of the target file.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the target doesn't match any revision of the media.
   */
  public function selectMediaRevisionForProvider(Media $media, $target, UserInterface $provider) {
    $storage = $this->entityTypeManager->getStorage('media');

    // If the target is a uuid, look for the corresponding file and ensure
    // it's the file of a revision of the media.
    if (Uuid::isValid($target)) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $this->loadFile($target);

      // Check if there is a revision of the media with that file.
      $ids = $storage
        ->getQuery()
        ->allRevisions()
        ->accessCheck(FALSE)
        ->condition($storage->getEntitytype()->getKey('id'), $media->id())
        ->condition('field_media_file', $file->id())
        ->execute();

      if (empty($ids)) {
        throw new BadRequestHttpException('The target is not a revision of the file');
      }
    }
    // Otherwise, we assume it's a revision id.
    elseif (is_numeric($target)) {
      $revision = $storage->loadRevision($target);
      if (empty($revision)) {
        throw new BadRequestHttpException('The target is not a revision of the file');
      }

      /** @var \Drupal\file\Entity\File $file */
      $file = $this->loadMediaFile($revision);
    }
    // If it's not a uuid or a revision id, throw an error.
    else {
      throw new BadRequestHttpException('The target is not a revision of the file');
    }

    // Delete any symlink hiding the media to the provider.
    $this->removeMediaSymlink($media, $provider, 'provider-hidden');

    // Create the new symlink to the specific version of the file.
    $this->createMediaSymlink($media, $file, $provider, 'provider');

    return $file->uuid();
  }

  /**
   * Find the list of symlinks for a media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param array $include
   *   List of patterns to include (all if empty):
   *   - public-latest
   *   - public-provider
   *   - public-provider-hidden
   *   - private-latest
   *   - private-provider
   *   - private-provider-hidden.
   *
   * @return array
   *   List of symlinks for the media.
   */
  public function findMediaSymlinks(Media $media, array $include = []) {
    static $patterns;

    if (!isset($patterns)) {
      $patterns = [];

      // Hexadecimal characters glob pattern.
      $characters = '[' . implode('', range(0, 9)) . implode('', range('a', 'f')) . ']';

      // Global pattern for a uuid.
      $uuid_pattern = implode('-', [
        str_repeat($characters, 8),
        str_repeat($characters, 4),
        str_repeat($characters, 4),
        str_repeat($characters, 4),
        str_repeat($characters, 12),
      ]);

      // Public and private path patterns.
      $bases = [
        'public' => $this->fileSystem->realpath('public://media'),
        'private' => $this->fileSystem->realpath('private://media'),
      ];

      foreach ($bases as $type => $base) {
        $patterns[$type . '-latest'] = $base . '/latest/';
        $patterns[$type . '-provider'] = $base . '/' . $uuid_pattern . '/';
        $patterns[$type . '-provider-hidden'] = $base . '/' . $uuid_pattern . '/hidden/';
      }
    }

    // Limit to the provided patterns.
    $selected_patterns = $patterns;
    if (!empty($patterns)) {
      $selected_patterns = array_diff_key($patterns, $include);
    }

    $path = $this->getMediaSymlinkPath($media);

    $links = [];
    foreach ($selected_patterns as $pattern) {
      foreach (glob($pattern . $path) as $link) {
        if (is_link($link)) {
          $links[] = $link;
        }
      }
    }
    return $links;
  }

  /**
   * Delete all the symlinks of a media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   */
  public function deleteMediaSymlinks(Media $media) {
    foreach ($this->findMediaSymlinks($media) as $link) {
      @unlink($link);
    }
  }

  /**
   * Regenerate the symlinks for the media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   */
  public function regenerateMediaSymlinks(Media $media) {
    $private = $this->mediaIsPrivate($media);
    $owner = $media->getOwner();

    // Determine the base path for the symlinks based on the private state.
    if ($private) {
      $base = $this->fileSystem->realpath('private://media');
    }
    else {
      $base = $this->fileSystem->realpath('public://media');
    }

    // File uuid of the latest version of the media.
    // @phpstan-ignore-next-line
    $latest_uuid = $this->loadMediaFile($media)->uuid();

    // List of symlinks to create.
    $links = [];

    // Media symlink path.
    $path = $this->getMediaSymlinkPath($media);

    // Add the link to the latest version.
    $links[$base . '/latest/' . $path] = $latest_uuid;

    // Retrieve the list of provider selected versions.
    $selection = $this->getMediaSelectedFileVersions($media);

    // Create the links specifics to providers.
    foreach ($selection as $provider_uuid => $target) {
      // Skip if the target is "latest" as we are already going to generate
      // a link to the latest version.
      if ($target === 'latest') {
        continue;
      }
      // Do not create a link for a provider if the media is private and the
      // the provider is not the owner.
      if ($private && $provider_uuid !== $owner->uuid()) {
        continue;
      }

      // Create a link specific to the provider to hide the media from the
      // provider. The actual target of the symlink doesn't matter as we just
      // check for the existing of the symlink in nginx to deny access to the
      // file.
      if ($target === 'hidden') {
        $links[$base . '/' . $provider_uuid . '/hidden/' . $path] = $latest_uuid;
      }
      // Create a link specific to the provider to a revision of the media.
      else {
        // Target is a file uuid.
        $links[$base . '/' . $provider_uuid . '/' . $path] = $target;
      }
    }

    // Delete the existing symlinks for the media.
    $this->deleteMediaSymlinks($media);

    // Create the new symlinks.
    $extension = pathinfo($media->getName(), PATHINFO_EXTENSION);
    foreach ($links as $link => $uuid) {
      $uri = $this->generateFileUri($uuid, $extension, $private);
      $target = $this->fileSystem->realpath($uri);
      $this->createSymlink($target, $link);
    }
  }

  /**
   * Get the symlink path for a media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   *
   * @return string
   *   Media symlink path in the form `/ab/cd/uuid.ext` where `ab` are the
   *   first 2 characters of the media uuid, `cd` are the third and fourth
   *   characters of the uuid.
   */
  public function getMediaSymlinkPath(Media $media) {
    $uuid = $media->uuid();
    $extension = pathinfo($media->getName(), PATHINFO_EXTENSION);

    // Link path.
    $path = substr($uuid, 0, 2);
    $path .= '/' . substr($uuid, 2, 2);
    $path .= '/' . $uuid . '.' . $extension;

    return $path;
  }

  /**
   * Generate a symlink link for the media.
   *
   * If the provided file is different than the one for the latest revision of
   * the media then we add the provider uuid to link.
   *
   * Important: the pattern for the link matches the pattern used in the nginx
   * configuration.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string $type
   *   One of those types:
   *   - latest: link to the latest file
   *   - provider: link specific to the provider to that file
   *   - provider-hidden: link specific to the provider to hide the media for
   *     the provider.
   *
   * @return string
   *   Symlink link.
   */
  public function generateSymlinkLink(Media $media, UserInterface $provider, $type = 'latest') {
    if ($provider->isAnonymous()) {
      return '';
    }
    // Generate a symlink in the public directory if the file is not private.
    elseif (!$this->mediaIsPrivate($media)) {
      $link = $this->fileSystem->realpath('public://') . '/media';
    }
    // Otherwise if the provider is the owner, generate a symlink in the private
    // directory.
    elseif ($this->providerIsOwner($media, $provider)) {
      $link = $this->fileSystem->realpath('private://') . '/media';
    }
    // Skip if the media is private and the provider is not the owner as it
    // means it doesn't have access to it.
    else {
      return '';
    }

    switch ($type) {
      // Create a symlink to the latest version of the file.
      case 'latest':
        $link .= '/latest';
        break;

      // Create a symlink specific to the provider to the given file. This
      // allows to create links to specific versions of a file for a given
      // provider. For example to enable a workflow where new versions of a file
      // must be validated before replacing the current file accessible via the
      // provider's site while perserving the permanent url.
      case 'provider':
        $link .= '/' . $provider->uuid();
        break;

      // Create a symlink specific to the provider than will be used to hide the
      // file from the provider. This acts as a flag for nginx to return a 404
      // for requests to the media by the provider. This allows to make a media
      // inaccessible on a site while available through others.
      case 'provider-hidden':
        $link .= '/' . $provider->uuid() . '/hidden';
        break;

      default:
        return '';
    }

    return $link . '/' . $this->getMediaSymlinkPath($media);
  }

  /**
   * Create a symlink between a media and a file revision of the media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\file\Entity\File|null $file
   *   File. If null, load the media's latest file.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string $type
   *   One of those types:
   *   - latest: link to the latest file
   *   - provider: link specific to the provider to that file
   *   - provider-hidden: link specific to the provider to hide the media for
   *     the provider.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   403 Access Denied if the provider was anonymous or the file was private
   *   and the provider was not the owner.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Internal server error if the symlink couldn't be created.
   */
  public function createMediaSymlink(Media $media, ?File $file, UserInterface $provider, $type) {
    $link = $this->generateSymlinkLink($media, $provider, $type);
    if (empty($link)) {
      throw new AccessDeniedHttpException('Unable to create link to file for this provider');
    }

    // If no file was given or a link to the latest version was required, load
    // the media's latest file.
    if (empty($file) || $type === 'latest') {
      $file = $this->loadMediaFile($media);
    }

    $this->createSymlink($this->fileSystem->realpath($file->getFileUri()), $link);
  }

  /**
   * Remove a media symlink.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param string $type
   *   One of those types:
   *   - latest: link to the latest file
   *   - provider: link specific to the provider to that file
   *   - provider-hidden: link specific to the provider to hide the media for
   *     the provider.
   */
  public function removeMediaSymlink(Media $media, UserInterface $provider, $type) {
    $link = $this->generateSymlinkLink($media, $provider, $type);
    if (!empty($link)) {
      @unlink($link);
    }
  }

  /**
   * Create a symlink to the taraget.
   *
   * @param string $target
   *   Target file path.
   * @param string $link
   *   Symlink path.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   500 Internal Server Error if the symlink couldn't be created.
   */
  public function createSymlink($target, $link) {
    // Ensure the directory exists.
    $this->prepareDirectory($link);

    // Remove any previous link.
    @unlink($link);

    // Create the symlink to the file.
    if (!@symlink($target, $link)) {
      throw new HttpException(500, 'Unable to create link to file');
    }
  }

  /**
   * Get media selected file versions.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   *
   * @return array
   *   Selected file versions keyed by provider uuids.
   */
  public function getMediaSelectedFileVersions(Media $media) {
    $list = [];
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    foreach ($media->get('selected_file_versions')->filterEmptyItems() as $item) {
      $list[$item->get('provider_uuid')->getValue()] = $item->get('target')->getValue();
    }
    return $list;
  }

  /**
   * Set media selected file versions.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param array $selection
   *   Selected file version per provider.
   */
  public function setMediaSelectedFileVersions(Media $media, array $selection) {
    $values = [];
    foreach ($selection as $provider_uuid => $target) {
      $values[] = [
        'provider_uuid' => $provider_uuid,
        'target' => $target,
      ];
    }
    $media->get('selected_file_versions')->setValue($values);
    $media->setNewRevision(FALSE);
    $media->save();
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

  /**
   * Load a media revision.
   *
   * @param string $uuid
   *   Media uuid.
   * @param string $revision_id
   *   File uuid, revision id or "latest".
   * @param bool $previous
   *   Load the revision before the given revision id. Only applies when
   *   revision_id is a revision id.
   *
   * @return \Drupal\media\Entity\Media|null
   *   Revision media entity or NULL if none was found.
   */
  public function loadMediaRevision($uuid, $revision_id, $previous = FALSE) {
    $storage = $this->entityTypeManager->getStorage('media');
    $entity_type = $storage->getEntityType();

    // Retrieve the correct revision id.
    $query = $storage
      ->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition($entity_type->getKey('uuid'), $uuid);

    // Load the latest revision.
    if ($revision_id === 'latest') {
      $query->latestRevision();
    }
    // Load the revision with the given file.
    elseif (Uuid::isValid($revision_id)) {
      $query->condition('field_media_file.entity:file.uuid', $revision_id);
    }
    // Load the revision before the given id.
    elseif ($previous) {
      $query->condition($entity_type->getKey('revision'), $revision_id, '<');
    }
    // Load the revision with the given id.
    else {
      $query->condition($entity_type->getKey('revision'), $revision_id);
    }

    $query->sort($entity_type->getKey('revision'), 'DESC');
    $query->range(0, 1);

    $ids = $query->execute();
    if (!empty($ids)) {
      /** @var \Drupal\media\Entity\Media */
      return $storage->loadRevision(array_key_first($ids));
    }

    return NULL;
  }

  /**
   * Delete a media revision.
   *
   * @param int $revision_id
   *   Media revision ID.
   */
  public function deleteMediaRevision($revision_id) {
    $this->entityTypeManager->getStorage('media')->deleteRevision($revision_id);
  }

}
