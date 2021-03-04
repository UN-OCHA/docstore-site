<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for me endpoint.
 */
class ProviderController extends ControllerBase {

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
      LoggerChannelFactoryInterface $logger_factory
    ) {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityRepository = $entityRepository;
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get info.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   API response.
   */
  public function getInfo(Request $request) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    $data = [
      'uuid' => $provider->uuid(),
      // @phpstan-ignore-next-line
      'dropfolder' => $provider->get('dropfolder')->value ?? '',
      // @phpstan-ignore-next-line
      'prefix' => $provider->get('prefix')->value ?? '',
      // @phpstan-ignore-next-line
      'shared_secret' => $provider->get('shared_secret')->value ?? '',
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Update info.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   API response.
   */
  public function updateInfo(Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    if (isset($params['shared_secret'])) {
      $provider->set('shared_secret', $params['shared_secret']);
      $provider->save();
    }

    $data = [
      'message' => 'Provider updated',
    ];

    return $this->createJsonResponse($data, 200);
  }

}
