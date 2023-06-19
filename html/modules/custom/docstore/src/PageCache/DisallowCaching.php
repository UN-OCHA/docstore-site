<?php

namespace Drupal\docstore\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\docstore\AuthenticationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cache policy for pages served from key auth.
 *
 * This policy disallows caching of requests that use docstore for security
 * reasons. Otherwise responses for authenticated requests can get into the
 * page cache and could be delivered to unprivileged users.
 */
class DisallowCaching implements RequestPolicyInterface {

  /**
   * The key auth service.
   *
   * @var \Drupal\docstore\AuthenticationService
   */
  protected $authenticationService;

  /**
   * Constructs a key authentication page cache policy.
   *
   * @param \Drupal\docstore\AuthenticationService $authenticationService
   *   The key auth service..
   */
  public function __construct(AuthenticationService $authenticationService) {
    $this->authenticationService = $authenticationService;
  }

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    if ($this->authenticationService->getKey($request)) {
      return self::DENY;
    }

    return NULL;
  }

}
