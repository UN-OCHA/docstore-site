<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\docstore\FileTrait;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for files endpoint.
 */
class DownloadController extends ControllerBase {

  use FileTrait;
  use ProviderTrait;
  use ResourceTrait;

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
   * Download media file.
   *
   * Downloads the latest published version of the file.
   *
   * @param string $media_uuid
   *   Media uuid.
   * @param string $provider_uuid
   *   Provider uuid.
   * @param string $hash
   *   Hash.
   * @param string $filename
   *   Filename.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Binary file response.
   *
   * @todo consolidate that with the MediaController::getMediaContent()?
   * @todo $filename is currently ignored.
   */
  public function downloadMedia($media_uuid, $provider_uuid, $hash, $filename, Request $request) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->loadMedia($media_uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->loadProvider($provider_uuid);

    // Check that the hash is valid.
    if ($hash !== $this->getFileUrlHash($media, $provider)) {
      throw new BadRequestHttpException('Hash does not match');
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadMediaFile($media, $provider);

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($file->getFilename()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Download file.
   *
   * Downloads the file, regardless if it's the latest version or not.
   *
   * @param string $file_uuid
   *   File uuid.
   * @param string $provider_uuid
   *   Provider uuid.
   * @param string $hash
   *   Hash.
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
  public function downloadFile($file_uuid, $provider_uuid, $hash, $filename, Request $request) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->loadFile($file_uuid);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->loadProvider($provider_uuid);

    // Check that the hash is valid.
    if ($hash !== $this->getFileUrlHash($file, $provider)) {
      throw new BadRequestHttpException('Hash does not match');
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($file->getFilename()) . '"',
      'Cache-Control' => 'private',
    ];

    return $this->createBinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Load a provider.
   *
   * @param string $uuid
   *   Provider uuid.
   *
   * @return \Drupal\user\UserInterface
   *   The provider if found.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   404 Not Found.
   */
  public function loadProvider($uuid) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->entityRepository->loadEntityByUuid('user', $uuid);
    if (empty($provider)) {
      throw new NotFoundHttpException('Provider does not exist');
    }
    return $provider;
  }

}
