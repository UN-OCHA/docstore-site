<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\docstore\UtilityTrait;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for files endpoint.
 */
class DownloadController extends ControllerBase {

  use UtilityTrait;

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
   * {@inheritdoc}
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager,
      EntityRepositoryInterface $entityRepository,
      EntityTypeManagerInterface $entityTypeManager,
      MimeTypeGuesser $mimeTypeGuesser,
      FileSystem $fileSystem,
      FileUsageInterface $fileUsage,
      LoggerChannelFactoryInterface $logger_factory
    ) {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityRepository = $entityRepository;
    $this->entityTypeManager = $entityTypeManager;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->fileSystem = $fileSystem;
    $this->fileUsage = $fileUsage;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Download a file.
   *
   * This endpoints emulates the nginx rules.
   *
   * @param string $uuid
   *   Media uuid.
   * @param string $filename
   *   Filename.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Binary file response.
   *
   * @todo consolidate that with the MediaController::getFileContent()?
   * @todo $filename is currently ignored.
   */
  public function downloadFile($uuid, $filename, Request $request) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);

    // Link path.
    $path = substr($uuid, 0, 2);
    $path .= '/' . substr($uuid, 2, 2);
    $path .= '/' . $uuid . '.' . $extension;

    // Retrieve the headers necessary to determine the version of the file.
    $provider_uuid = $this->getProviderUuidHeaderValue($request);

    // Look for public file matching the media uuid.
    $base = $this->fileSystem->realpath('public://') . '/media';
    $file = $this->lookForFile($base, $provider_uuid, $path);
    $private = FALSE;

    // If there is no public file, look for a private one.
    if (empty($file)) {
      // Look for private file matching the media uuid.
      $base = $this->fileSystem->realpath('private://') . '/media';
      $file = $this->lookForFile($base, $provider_uuid, $path);
      $private = TRUE;
    }

    // If no public or private file was found, return a 404.
    if (empty($file)) {
      throw new NotFoundHttpException('File not found');
    }
    // Otherwise if the file is private, check access.
    elseif ($private) {
      static::checkMediaAccess($uuid, $request);
    }

    $headers = [
      'Content-Type' => $this->mimeTypeGuesser->guess($file),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($filename) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file, 200, $headers);
  }

  /**
   * Get the value of the X-Docstore-Provider-Uuid header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return string
   *   The provider uuid if defined.
   */
  public function getProviderUuidHeaderValue(Request $request) {
    if ($request->headers->has('X-Docstore-Provider-Uuid')) {
      $value = $request->headers->get('X-Docstore-Provider-Uuid');
      // It's supposed to be a uuid.
      if (Uuid::isValid($value)) {
        return $value;
      }
    }
    return '';
  }

  /**
   * Look for the file to download.
   *
   * @param string $base
   *   Base path.
   * @param string $provider_uuid
   *   Provider uuid.
   * @param string $path
   *   Path to the media link.
   *
   * @return string
   *   Path to the file if found.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   404 Not Found if the media is hidden for the provider.
   */
  public function lookForFile($base, $provider_uuid, $path) {
    $file = '';

    if (!empty($provider_uuid)) {
      // If the file is hidden for the provider, return a 404.
      if (is_link($base . '/' . $provider_uuid . '/hidden/' . $path)) {
        throw new NotFoundHttpException('File not found');
      }

      // Try to retrieve the file specific to the provider.
      $file = $this->getFilePathFromLink($base . '/' . $provider_uuid . '/' . $path);
    }

    // Try to retrieve the file for the latest version of the media.
    if (empty($file)) {
      $file = $this->getFilePathFromLink($base . '/latest/' . $path);
    }

    return $file;
  }

  /**
   * Resolve a symlink and return its target file path if it exists.
   *
   * @param string $link
   *   Media symlink.
   *
   * @return string
   *   Linked file path.
   */
  public function getFilePathFromLink($link) {
    if (is_link($link)) {
      $target = @readlink($link);
      if (!empty($target) && file_exists($target)) {
        return $target;
      }
    }
    return '';
  }

  /**
   * Check access to the media.
   *
   * @param string $uuid
   *   Media uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   403 Access Denied if the provider doesn't have access to the media.
   */
  public static function checkMediaAccess($uuid, Request $request) {
    $response = \Drupal::service('docstore.file_controller')
      ->checkFileAccess($uuid, $request);
    if ($response->getStatusCode() != 200) {
      throw new AccessDeniedHttpException('Access denied');
    }
  }

}
