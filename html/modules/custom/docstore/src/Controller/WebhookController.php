<?php

namespace Drupal\docstore\Controller;

use Drupal\webhooks\Entity\WebhookConfig;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * API controller for webhook endpoint.
 */
class WebhookController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Get hooks.
   */
  public function getWebhooks(Request $request) {
    $data = [];

    // Get provider.
    $provider = $this->getProvider();

    $query = $this->entityTypeManager->getStorage('webhook_config')->getQuery();
    $ids = $query->execute();
    $webhooks_configs = WebhookConfig::loadMultiple($ids);
    /** @var \Drupal\webhooks\Entity\WebhookConfigInterface $webhook_config */
    foreach ($webhooks_configs as $webhook_config) {
      if ($webhook_config->getThirdPartySetting('docstore', 'base_provider_uuid') === $provider->uuid()) {
        $data[] = [
          'display_name' => $webhook_config->label(),
          'machine_name' => $webhook_config->id(),
          'type' => $this->t('@type', ['@type' => $webhook_config->getType()]),
          'status' => $webhook_config->status() ? $this->t('active') : $this->t('inactive'),
          'payload_url' => $webhook_config->getPayloadUrl(),
          'events' => array_keys($webhook_config->getEvents()),
        ];
      }
    }

    $response = new JsonResponse($data);
    $response->setStatusCode(200);

    return $response;
  }

  /**
   * Create hook.
   */
  public function createWebhook(Request $request) {
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
    $webhook_config->setThirdPartySetting('docstore', 'base_provider_uuid', $provider->uuid());
    $webhook_config->save();

    // Invalidate cache.
    Cache::invalidateTags(['webhooks']);

    $data = [
      'message' => 'Webhook created',
      'machine_name' => $id,
    ];

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Delete a hook.
   */
  public function deleteWebhook($id, Request $request) {
    // Get provider.
    $provider = $this->getProvider();

    // Check for duplicate.
    $webhook_config = WebhookConfig::load($id);

    if ($webhook_config->getThirdPartySetting('docstore', 'base_provider_uuid') !== $provider->uuid()) {
      throw new BadRequestHttpException('Webhook is not owned by you');
    }

    $webhook_config->delete();

    $data = [
      'message' => 'Webhook deleted',
    ];

    // Invalidate cache.
    Cache::invalidateTags(['webhooks']);

    $response = new JsonResponse($data);

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
