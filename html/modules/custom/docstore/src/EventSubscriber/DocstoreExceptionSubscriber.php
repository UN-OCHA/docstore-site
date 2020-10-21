<?php

namespace Drupal\docstore\EventSubscriber;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Routing\RouteMatch;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The event subscriber preventing config to be exported.
 */
final class DocstoreExceptionSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // React early on export and late on import.
    return [
      KernelEvents::EXCEPTION => 'onException',
    ];
  }

  /**
   * Handles errors for this subscriber.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The event to process.
   */
  public function onException(GetResponseForExceptionEvent $event) {
    $request = $event->getRequest();
    $route = RouteMatch::createFromRequest($request);

    // Make sure it's an API request.
    if (!$route->getRouteObject() || !$route->getRouteObject()->hasOption('docstore_crud')) {
      return;
    }

    $exception = $event->getException();
    $request->attributes->set('exception', $exception);

    // If the exception is cacheable, generate a cacheable response.
    if ($exception instanceof CacheableDependencyInterface) {
      $response = new CacheableJsonResponse(['message' => $event->getException()->getMessage()], $exception->getStatusCode(), $exception->getHeaders());
      $response->addCacheableDependency($exception);
    }
    else {
      $response = new JsonResponse(['message' => $event->getException()->getMessage()], $exception->getStatusCode(), $exception->getHeaders());
    }

    $event->setResponse($response);
  }

}
