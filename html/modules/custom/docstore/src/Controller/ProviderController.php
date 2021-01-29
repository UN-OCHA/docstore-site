<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
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
   * Get info.
   */
  public function getInfo() {
    /** @var \Drupal\user\Entity\User $provider */
    $provider = $this->requireProvider();

    $data = [
      'uuid' => $provider->uuid(),
      'dropfolder' => $provider->get('dropfolder')->value ?? '',
      'prefix' => $provider->get('prefix')->value ?? '',
      'shared_secret' => $provider->get('shared_secret')->value ?? '',
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Update info.
   */
  public function updateInfo(Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\user\Entity\User $provider */
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
