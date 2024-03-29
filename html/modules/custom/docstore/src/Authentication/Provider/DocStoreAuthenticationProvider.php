<?php

namespace Drupal\docstore\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\docstore\AuthenticationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Document store authentication provider.
 */
class DocStoreAuthenticationProvider implements AuthenticationProviderInterface {

  /**
   * The authentication service.
   *
   * @var \Drupal\docstore\AuthenticationService
   */
  protected $authenticationService;

  /**
   * Constructs a key authentication provider object.
   *
   * @param \Drupal\docstore\AuthenticationService $authenticationService
   *   The key auth service.
   */
  public function __construct(AuthenticationService $authenticationService) {
    $this->authenticationService = $authenticationService;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return (bool) $this->authenticationService->getKey($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    // @todo Add flood protection.
    // Get the provided key.
    if ($key = $this->authenticationService->getKey($request)) {
      // Find the linked user.
      if ($user = $this->authenticationService->getProviderByKey($key)) {
        return $user;
      }
    }

    return NULL;
  }

}
