<?php

namespace Drupal\docstore;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provider related functions.
 */
trait ProviderTrait {

  use UtilityTrait;

  /**
   * Get provider.
   *
   * @return \Drupal\user\UserInterface
   *   The provider if found.
   */
  protected function getProvider() {
    /** @var \Drupal\Core\Session\AccountProxyInterface $current_user */
    $current_user = $this->currentUser();

    // Try to load the user.
    if (!$current_user->isAnonymous()) {
      /** @var \Drupal\user\UserInterface|null $provider */
      $provider = User::load($current_user->id());
    }

    return isset($provider) ? $provider : User::getAnonymousUser();
  }

  /**
   * Require provider.
   *
   * @return \Drupal\user\UserInterface
   *   The provider.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   403 Access Denied if no valid provider is found.
   */
  protected function requireProvider() {
    $provider = $this->getProvider();

    if ($provider->isAnonymous()) {
      throw new AccessDeniedHttpException('Provider is required');
    }

    return $provider;
  }

  /**
   * Check if provider can read content of the given resource type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityBundleBase $resource_type
   *   Docstore resource type (node type or vocabulary).
   * @param \Drupal\user\UserInterface|null $provider
   *   Provider.
   *
   * @return bool
   *   TRUE if the provider is allowed to create content for the resource_type.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   403 Access Denied if no valid provider is found or if the provider
   *   doesn't have read access to the resource.
   */
  protected function providerCanRead(ConfigEntityBundleBase $resource_type, ?UserInterface $provider = NULL) {
    // Special case for the file media type which is always accessible as a
    // resource type. Media and file entities may on the hand be private.
    if ($resource_type->id() === 'file') {
      return TRUE;
    }

    // Shared resources are always readable.
    if ($resource_type->getThirdPartySetting('docstore', 'shared')) {
      return TRUE;
    }

    // Otherwise a provider is required.
    $provider = $provider ?: $this->requireProvider();

    // Check if the current provider is the provider of the resource type.
    if ($resource_type->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      return TRUE;
    }

    throw new AccessDeniedHttpException(strtr('You do not have access to read @label resources', [
      '@label' => $resource_type->label(),
    ]));
  }

  /**
   * Check if the provider is allowed to create content for the resource type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityBundleBase $resource_type
   *   Docstore resource type (node type or vocabulary).
   * @param \Drupal\user\UserInterface|null $provider
   *   Provider.
   *
   * @return bool
   *   TRUE if the provider is allowed to create content for the resource.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   403 Access Denied if no valid provider is found or if the provider
   *   doesn't have create/update/delete access to the resource.
   */
  protected function providerCanCreateUpdateDelete(ConfigEntityBundleBase $resource_type, ?UserInterface $provider = NULL) {
    // A provider is required to create, update or delete a resource.
    $provider = $provider ?: $this->requireProvider();

    // Check if the current provider is the provider of the resource.
    if ($resource_type->getThirdPartySetting('docstore', 'provider_uuid') == $provider->uuid()) {
      return TRUE;
    }

    // Otherwise check if other providers are allowed to create, update or
    // delete this type of resource.
    if ($resource_type->getThirdPartySetting('docstore', 'content_allowed', FALSE)) {
      return TRUE;
    }

    throw new AccessDeniedHttpException('You do not have access to create, update or delete @label resources', [
      '@label' => $resource_type->label(),
    ]);
  }

  /**
   * Check if the provider is the owner of the resource.
   *
   * @param \Drupal\Core\Entity\EntityInterface $resource
   *   Docstore resource (node, term, file etc.).
   * @param \Drupal\user\UserInterface|null $provider
   *   Provider.
   * @param string $check_type
   *   Type of check to perform:
   *   - owner_id: check the owner id against the current provider's id.
   *   - provider_uuid: check the uuid of the resource's provider against
   *     the current provider's uuid.
   *   - base_provider_uuid: check the `base_provider_uuid` third party setting
   *     against the current provider's uuid.
   * @param bool $throw
   *   Whether to throw an Access Denied exception or return FALSE if the
   *   provider is not the owner of the resource.
   *
   * @return bool
   *   TRUE if the provider is the owner of the resource.
   */
  protected function providerIsOwner(EntityInterface $resource, ?UserInterface $provider = NULL, $check_type = 'owner_id', $throw = TRUE) {
    $is_owner = FALSE;

    $provider = $provider ?: $this->requireProvider();

    if (!$provider->isAnonymous()) {
      switch ($check_type) {
        case 'owner_id':
          if ($resource instanceof EntityOwnerInterface) {
            // Note: no strict equality for ids as they can be strings or ints.
            $is_owner = $resource->getOwnerId() == $provider->id();
          }
          break;

        case 'provider_uuid':
          if ($resource instanceof FieldableEntityInterface) {
            /** @var \Drupal\entity_reference_uuid\EntityReferenceUuidFieldItemListInterface $provider_uuid */
            $provider_uuid = $resource->get('provider_uuid');
            if (!empty($provider_uuid)) {
              $is_owner = $provider_uuid->entity->uuid() === $provider->uuid();
            }
          }
          break;

        case 'base_provider_uuid':
          if ($resource instanceof ConfigEntityInterface) {
            $is_owner = $resource->getThirdPartySetting('docstore', 'base_provider_uuid') === $provider->uuid();
          }
          break;
      }
    }

    if (!$is_owner && $throw) {
      throw new AccessDeniedHttpException(strtr('@type is not owned by you', [
        '@type' => $this->getResourceTypeLabel($resource->getEntityTypeId(), FALSE),
      ]));
    }

    return $is_owner;
  }

}
