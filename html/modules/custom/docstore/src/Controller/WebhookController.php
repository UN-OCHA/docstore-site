<?php

namespace Drupal\docstore\Controller;

use Drupal\webhooks\Entity\WebhookConfig;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class WebhookController.
 */
class WebhookController extends ControllerBase {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Create hook.
   */
  public function createHook(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);

    // Check required fields.
    if (empty($params['label'])) {
      throw new BadRequestHttpException('Label is required');
    }

    if (empty($params['payload_url'])) {
      throw new BadRequestHttpException('Payload URL is required');
    }

    if (empty($params['secret'])) {
      $params['secret'] = NULL;
    }

    // Filter events.
    $allowed_events = [];
    docstore_webhooks_event_info_alter($allowed_events);

    if (empty($params['events'])) {
      $params['events'] = array_combine(array_keys($allowed_events), array_keys($allowed_events));
    }

    // Get provider.
    $provider = $this->getProvider();

    // Construct Id.
    $id = $params['label'];
    $id = strtolower($id);
    $id = preg_replace('/[^a-z0-9_]+/', '_', $id);
    $id = preg_replace('/_+/', '_', $id);
    $id = $provider->get('prefix')->value . $id;
    $id = trim(substr($id, 0, 127), '_');

    // Create webhook config.
    $item = [
      'type' => 'outgoing',
      'content_type' => 'application/json',
      'status' => TRUE,
      'non_blocking' => TRUE,
      'label' => $params['label'],
      'payload_url' => $params['payload_url'],
      'secret' => $params['secret'],
      'id' => $id,
      'events' => $params['events'],
    ];

    // Check for duplicate.
    if ($webhook_config = WebhookConfig::load($id)) {
      throw new BadRequestHttpException('Webhook already exists');
    }

    /** @var \Drupal\webhooks\Entity\WebhookConfig $webhook_config */
    $webhook_config = WebhookConfig::create($item);
    $webhook_config->save();

    // Invalidate cache.
    Cache::invalidateTags(['webhooks']);

    $data = [
      'message' => 'Webhook created',
      'uuid' => $webhook_config->uuid(),
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Get provider.
   */
  protected function getProvider() {
    /** @var Drupal\Core\Session\AccountProxy $current_user */
    $current_user = $this->currentUser();
    $provider = $current_user->getAccount();

    if (!$provider) {
      throw new BadRequestHttpException('Provider is required');
    }

    return $provider;
  }

}
