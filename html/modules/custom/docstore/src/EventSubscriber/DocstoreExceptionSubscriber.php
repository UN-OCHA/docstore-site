<?php

namespace Drupal\docstore\EventSubscriber;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Routing\RouteMatch;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Wrap exceptions inside json.
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
      if (!$exception instanceof HttpException) {
        $response = new CacheableJsonResponse(['message' => $exception->getMessage()], 400);
      }
      else {
        $response = new CacheableJsonResponse(['message' => $exception->getMessage()], $exception->getStatusCode(), $exception->getHeaders());
      }
      $response->addCacheableDependency($exception);
    }
    else {
      if (!$exception instanceof HttpException) {
        $response = new JsonResponse(['message' => $exception->getMessage()], 400);
      }
      else {
        $response = new JsonResponse(['message' => $exception->getMessage()], $exception->getStatusCode(), $exception->getHeaders());
      }
    }

    $event->setResponse($response);
  }

}
