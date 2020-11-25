<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class ProviderController.
 */
class ProviderController extends ControllerBase {

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

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Update info.
   */
  public function UpdateInfo(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    /** @var \Drupal\user\Entity\User $provider */
    $provider = $this->requireProvider();

    if (isset($params['shared_secret'])) {
      $provider->set('shared_secret', $params['shared_secret']);
      $provider->save();
    }

    $data = [
      'message' => 'Provider updated',
    ];

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Get provider.
   */
  protected function requireProvider() {
    /** @var Drupal\Core\Session\AccountProxy $current_user */
    $current_user = $this->currentUser();
    $provider = $current_user->getAccount();

    if ($current_user->isAnonymous() || !$provider) {
      throw new BadRequestHttpException('Provider is required');
    }

    return $provider;
  }

}
