<?php

namespace Drupal\docstore;

// @todo consider creating a docstore `Resource`?
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provider related functions.
 */
trait ProviderTrait {

  /**
   * Get provider.
   *
   * @return \Drupal\user_bundle\Entity\TypedUser|false
   *   The provider if found.
   */
  protected function getProvider() {
    /** @var Drupal\Core\Session\AccountProxy $current_user */
    $current_user = $this->currentUser();
    $provider = $current_user->getAccount();

    return $provider;
  }

  /**
   * Require provider.
   *
   * @return \Drupal\user_bundle\Entity\TypedUser
   *   The provider if found.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   403 Access Denied if no valid provider is found.
   */
  protected function requireProvider() {
    $provider = $this->getProvider();

    if (!$provider || $provider->isAnonymous()) {
      throw new AccessDeniedHttpException('Provider is required');
    }

    return $provider;
  }

  /**
   * Check if provider can read content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $resource
   *   Docstore resource (node type or vocabulary).
   *
   * @return bool
   *   TRUE if the provider is allowed to create content for the resource.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   403 Access Denied if no valid provider is found or if the provider
   *   doesn't have read access to the resource.
   */
  protected function providerCanRead(EntityInterface $resource) {
    // Shared resources are always readable.
    if ($resource->getThirdPartySetting('docstore', 'shared')) {
      return TRUE;
    }

    // Otherwise a provider is required.
    $provider = $this->requireProvider();

    // Check if the current provider is the provider of the resource.
    if ($type->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      return TRUE;
    }

    throw new AccessDeniedHttpException('You do not have access to read @label resources', [
      '@label' => $resource->label(),
    ]);
  }

  /**
   * Check if the provider is allowed to create content for the resource.
   *
   * @param \Drupal\Core\Entity\EntityInterface $resource
   *   Docstore resource (node type or vocabulary).
   *
   * @return bool
   *   TRUE if the provider is allowed to create content for the resource.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   403 Access Denied if no valid provider is found or if the provider
   *   doesn't have create/update/delete access to the resource.
   */
  protected function providerCanCreateUpdateDelete(EntityInterface $resource) {
    // A provider is required to create, update or delete a resource.
    $provider = $this->requireProvider();

    // Check if the current provider is the provider of the resource.
    if ($resource->getThirdPartySetting('docstore', 'provider_uuid') == $provider->uuid()) {
      return TRUE;
    }

    // Otherwise check if other providers are allowed to create, update or
    // delete this type of resource.
    if ($resource->getThirdPartySetting('docstore', 'content_allowed', FALSE)) {
      return TRUE;
    }

    throw new AccessDeniedHttpException('You do not have access to create, update or delete @label resources', [
      '@label' => $resource->label(),
    ]);
  }

  /**
   * Check if the provider is the owner of the resource.
   *
   * @param \Drupal\Core\Entity\EntityInterface $resource
   *   Docstore resource (node, term, file etc.).
   * @param string $check_type
   *   Type of check to perform:
   *   - owner_id: check the owner id against the current provider's id.
   *   - provider_uuid: check the uuid of the resource's provider against
   *     the current provider's uuid.
   *   - base_provider_uuid: check the `base_provider_uuid` third party setting
   *     against the current provider's uuid.
   *
   * @return bool
   *   TRUE if the provider is the owner of the resource.
   */
  protected function providerIsOwner(EntityInterface $resource, $check_type = 'owner_id') {
    $provider = $this->requireProvider();

    switch ($resource->getEntityTypeId()) {
      case 'node':
        $type = 'Document';
        break;

      case 'taxonomy_term':
        $type = 'Term';
        break;

      case 'webhook_config':
        $type = 'Webhook';
        break;

      default:
        $type = ucfirst($resource->getEntityTypeId());
    }

    $is_owner = FALSE;

    switch ($check_type) {
      case 'owner_id':
        $is_owner = $resource->getOwnerId() === $provider->id();
        break;

      case 'provider_uuid':
        $is_owner = $resource->provider_uuid->entity->uuid() === $provider->uuid();
        break;

      case 'base_provider_uuid':
        $is_owner = $resource->getThirdPartySetting('docstore', 'base_provider_uuid') === $provider->uuid();
        break;
    }

    if ($is_owner) {
      return TRUE;
    }

    throw new AccessDeniedHttpException(strtr('@type is not owned by you', [
      '@type' => $type,
    ]));
  }

}
