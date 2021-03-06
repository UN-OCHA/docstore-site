<?php

namespace Drupal\docstore\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\Routing\Route;

/**
 * Checks access for API calls.
 */
class DocStoreAccessController implements AccessInterface {

  /**
   * Access check.
   *
   * @param \Drupal\Core\Session\AccountProxy $account
   *   Run access checks for this account.
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   Matched route.
   * @param \Symfony\Component\Routing\Route $route
   *   Route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountProxy $account, RouteMatch $route_match, Route $route) {
    // Assume read.
    $crud = 'R';
    if ($route->hasOption('docstore_crud')) {
      $crud = $route->getOption('docstore_crud');
    }

    // Assume basic access.
    $access_level = 'B';
    if ($route->hasOption('docstore_access_level')) {
      $access_level = $route->getOption('docstore_access_level');
    }

    // Get real account.
    $provider = $account->getAccount();

    // If no API key is used, no info is available.
    if (!isset($provider->docstore_write)) {
      // @phpstan-ignore-next-line
      $provider->docstore_write = FALSE;
      // Any non read operation isn't allowed.
      if ($crud !== 'R') {
        return AccessResult::forbidden();
      }

      // Any calls to non-basic endpoints is denied.
      if ($access_level !== 'B') {
        return AccessResult::forbidden();
      }
    }

    // Check access.
    if (!$provider->docstore_write) {
      if ($crud !== 'R') {
        return AccessResult::forbidden();
      }
    }

    return AccessResult::allowed();
  }

}
