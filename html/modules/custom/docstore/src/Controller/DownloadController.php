<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller for files endpoint.
 */
class DownloadController extends ControllerBase {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entityRepository,
      LoggerChannelFactoryInterface $logger_factory
    ) {
    $this->entityRepository = $entityRepository;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Download media file.
   *
   * Downloads the latest published version of the file.
   */
  public function downloadMedia($media_uuid, $provider_uuid, $hash, $filename) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityRepository->loadEntityByUuid('media', $media_uuid);
    if (!$media) {
      throw new BadRequestHttpException('Media does not exist');
    }

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($media->getSource()->getSourceFieldValue($media));

    /** @var \Drupal\user\Entity\User $provider */
    $provider = $this->entityRepository->loadEntityByUuid('user', $provider_uuid);
    if (!$provider) {
      throw new BadRequestHttpException('Provider does not exist');
    }

    if (empty($provider->get('shared_secret')->value)) {
      throw new BadRequestHttpException('No shared secret defined');
    }

    $calculated_hash = md5($provider->get('shared_secret')->value . $file_uuid . $provider_uuid);
    if ($hash !== $calculated_hash) {
      throw new BadRequestHttpException('Hash does not match');
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($file->getFilename()) . '"',
      'Cache-Control' => 'private',
    ];

    return new BinaryFileResponse($file->getFileUri(), 200, $headers);
  }

  /**
   * Download file.
   *
   * Downloads the file, regardless if it's the latest version or not.
   */
  public function downloadFile($file_uuid, $provider_uuid, $hash, $filename) {
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $file_uuid);
    if (!$file) {
      throw new BadRequestHttpException('File does not exist');
    }

    /** @var \Drupal\user\Entity\User $provider */
    $provider = $this->entityRepository->loadEntityByUuid('user', $provider_uuid);
    if (!$provider) {
      throw new BadRequestHttpException('Provider does not exist');
    }

    if (empty($provider->get('shared_secret')->value)) {
      throw new BadRequestHttpException('No shared secret defined');
    }

    $calculated_hash = md5($provider->get('shared_secret')->value . $file_uuid . $provider_uuid);
    if ($hash !== $calculated_hash) {
      throw new BadRequestHttpException('Hash does not match');
    }

    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Disposition' => 'attachment; filename="' . Unicode::mimeHeaderEncode($file->getFilename()) . '"',
      'Cache-Control' => 'private',
    ];

    return new BinaryFileResponse($file->getFileUri(), 200, $headers);
  }

}
