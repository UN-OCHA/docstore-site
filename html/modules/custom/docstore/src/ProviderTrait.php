<?php

namespace Drupal\docstore;

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
   * @return \Drupal\user_bundle\Entity\TypedUser|false
   *   The provider if found.
   */
  protected function requireProvider() {
    $provider = $this->getProvider();

    if (!$provider || $provider->isAnonymous()) {
      throw new \Exception('Provider is required');
    }

    return $provider;
  }

}
