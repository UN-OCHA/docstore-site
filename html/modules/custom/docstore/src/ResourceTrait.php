<?php

namespace Drupal\docstore;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Resource related functions.
 */
trait ResourceTrait {

  /**
   * Get the request content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API Request.
   *
   * @return array
   *   Request content.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Throw a 400 when the request doesn't have a valid JSON content.
   */
  public function getRequestContent(Request $request) {
    $content = json_decode($request->getContent(), TRUE);
    if (empty($content) || !is_array($content)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }
    return $content;
  }

  /**
   * Send a JSON response with the given data and status code.
   *
   * @param mixed $data
   *   Response data.
   * @param int $code
   *   Status code.
   * @param array $headers
   *   Response headers.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function createJsonResponse($data = NULL, $code = 200, array $headers = []) {
    $response = new JsonResponse($data, $code, $headers);
    $response->setStatusCode($code);
    return $response;
  }

  /**
   * Send a cacheable JSON response with the given data and status code.
   *
   * @param array $cache
   *   Array of cache data with the following optional keys: tags, contexts and
   *   max-age.
   * @param mixed $data
   *   Response data.
   * @param int $code
   *   Status code.
   * @param array $headers
   *   Response headers.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response.
   */
  public function createCacheableJsonResponse(array $cache = [], $data = NULL, $code = 200, array $headers = []) {
    $response = new CacheableJsonResponse($data, $code, $headers);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray([
      '#cache' => $cache,
    ]));
    return $response;
  }

  /**
   * Send a Binary response with the given data and status code.
   *
   * @param \SplFileInfo|string $file
   *   The file to stream.
   * @param int $code
   *   Status code.
   * @param array $headers
   *   Response headers.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   JSON response.
   */
  public function createBinaryFileResponse($file, $code = 200, array $headers = []) {
    $response = new BinaryFileResponse($file, 200, $headers);
    return $response;
  }

}
