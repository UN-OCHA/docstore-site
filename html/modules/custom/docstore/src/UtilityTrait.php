<?php

namespace Drupal\docstore;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Utility functions that don't depend on injected services.
 */
trait UtilityTrait {

  /**
   * Wait endpoint.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function wait() {
    sleep(1);

    return $this->createJsonResponse([], 200);
  }

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
   * Get the pagination (offset, limit) from the request parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API Request.
   * @param int $max
   *   Maximum number of resources to return.
   *
   * @return array
   *   Array with the offset and limit.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Throw a 400 when the offset or limit is invalid.
   */
  public function getRequestPagination(Request $request, $max = 100) {
    $offset = $request->query->getInt('offset', 0);
    $limit = $request->query->getInt('limit', $max);

    if ($offset < 0) {
      throw new BadRequestHttpException('Offset parameter must be a positive integer');
    }

    if ($limit <= 0 || $limit > $max) {
      throw new BadRequestHttpException(strtr('Limit parameter must be between 1 and @max', [
        '@max' => $max,
      ]));
    }

    return [$offset, $limit];
  }

  /**
   * Create the cache dependency for the response.
   *
   * @param bool $user_context
   *   Whether to add a cache context on the user or not. Default to TRUE
   *   because most API requests vary based on the user that performed it.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Cache metadata.
   */
  public function createResponseCache($user_context = TRUE) {
    $cache = new CacheableMetadata();
    if ($user_context) {
      $cache->addCacheContexts(['user']);
    }
    return $cache;
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
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
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
  public function createCacheableJsonResponse(CacheableMetadata $cache, $data = NULL, $code = 200, array $headers = []) {
    $response = new CacheableJsonResponse($data, $code, $headers);
    $response->addCacheableDependency($cache);
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
   *   Binary file response.
   */
  public function createBinaryFileResponse($file, $code = 200, array $headers = []) {
    $response = new BinaryFileResponse($file, 200, $headers);
    return $response;
  }

  /**
   * Get the resource type from the entity type.
   *
   * @param string $entity_type_id
   *   Entity type of the resource.
   *
   * @return string
   *   Resource type.
   */
  public function getResourceType($entity_type_id) {
    switch ($entity_type_id) {
      case 'node_type':
        return 'document_types';

      case 'taxonomy_vocabulary':
        return 'vocabularies';

      case 'media_type':
        return 'media_types';

      case 'node':
        return 'documents';

      case 'taxonomy_term':
        return 'terms';

      case 'media':
        return 'media';

      case 'file':
        return 'files';

      case 'webhook_config':
        return 'webhooks';
    }
    return $entity_type_id;
  }

  /**
   * Get the resource type label from the entity type.
   *
   * @param string $entity_type_id
   *   Entity type of the resource.
   * @param bool $plural
   *   Return the plural label.
   * @param bool $lower_case
   *   Whether to return the lower case version of the resource type.
   *
   * @return string
   *   Resource type label.
   */
  public function getResourceTypeLabel($entity_type_id, $plural = TRUE, $lower_case = FALSE) {
    switch ($entity_type_id) {
      case 'node_type':
        $label = $plural ? 'document types' : 'document type';
        break;

      case 'taxonomy_vocabulary':
        $label = $plural ? 'vocabularies' : 'vocabulary';
        break;

      case 'media_type':
        $label = $plural ? 'media types' : 'media type';
        break;

      case 'node':
        $label = $plural ? 'documents' : 'document';
        break;

      case 'taxonomy_term':
        $label = $plural ? 'terms' : 'term';
        break;

      case 'media':
        $label = $plural ? 'media' : 'media';
        break;

      case 'file':
        $label = $plural ? 'files' : 'file';
        break;

      case 'webhook_config':
        $label = $plural ? 'webhooks' : 'webhook';
        break;

      default:
        $label = str_replace('_', ' ', $entity_type_id);
        $label = $plural ? $label . 's' : $label;
    }

    return $lower_case ? $label : ucfirst($label);
  }

  /**
   * Check if a file is private.
   *
   * @param \Drupal\file\Entity\File $file
   *   File.
   *
   * @return bool
   *   TRUE if the file is private.
   */
  public function fileIsPrivate(File $file) {
    return $this->uriIsPrivate($file->getFileUri());
  }

  /**
   * Check if a uri is private.
   *
   * @param string $uri
   *   URI.
   *
   * @return bool
   *   TRUE if the uri is private.
   */
  public function uriIsPrivate($uri) {
    return StreamWrapperManager::getScheme($uri) === 'private';
  }

  /**
   * Get the direct URL of a file.
   *
   * @param \Drupal\file\Entity\File $file
   *   File.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $relative
   *   Whether to return an absolute URL or a relative one.
   *
   * @return string
   *   Direct URL.
   */
  public function getFileDirectUrl(File $file, UserInterface $provider, $relative = FALSE) {
    return $this->createDirectUrl('files', $file->uuid(), $file->getFilename(), $provider, $relative);
  }

  /**
   * Get the direct URL of a media.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $relative
   *   Whether to return an absolute URL or a relative one.
   *
   * @return string
   *   Direct URL.
   */
  public function getMediaDirectUrl(Media $media, UserInterface $provider, $relative = FALSE) {
    return $this->createDirectUrl('media', $media->uuid(), $media->getName(), $provider, $relative);
  }

  /**
   * Create the direct URL of a media.
   *
   * @param string $base
   *   Base path for the url (ex: media or files).
   * @param string $uuid
   *   Entity uuid (media or file).
   * @param string $filename
   *   File name.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $relative
   *   Whether to return an absolute URL or a relative one.
   *
   * @return string
   *   Direct URL.
   */
  public function createDirectUrl($base, $uuid, $filename, UserInterface $provider, $relative = FALSE) {
    try {
      $hash = $this->createFileUrlHash($uuid, $provider);
    }
    catch (BadRequestHttpException $exception) {
      return '';
    }
    $uri = '/' . $base . '/' . $uuid . '/' . $provider->uuid() . '/' . $hash . '/' . $filename;

    // This will generate an absolute URL. We wrap the URL generation into
    // its own renderer to avoid "early rendering" exceptions due to cache
    // metadata not being captured. The createFileUrl() method below has a bit
    // more explanation.
    //
    // @see \Drupal\docstore\FileTrait::createFileUrl()
    // @see https://www.drupal.org/node/2513810
    // @see https://www.drupal.org/node/2638686
    return \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use ($uri, $relative) {
      return Url::fromUserInput($uri, ['absolute' => !$relative])->toString(FALSE);
    });
  }

  /**
   * Get the hash for a direct file URL.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity (media or file).
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return string
   *   Hash.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the provider doesn't have a defined shared secret.
   */
  public function getFileUrlHash(EntityInterface $entity, UserInterface $provider) {
    return $this->createFileUrlHash($entity->uuid(), $provider);
  }

  /**
   * Create the hash for a direct file URL.
   *
   * @param string $uuid
   *   UUID of the media or file entity.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return string
   *   Hash.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if the provider doesn't have a defined shared secret.
   */
  public function createFileUrlHash($uuid, UserInterface $provider) {
    if (!$provider->hasField('shared_secret') || empty($provider->get('shared_secret')->value)) {
      throw new BadRequestHttpException('No shared secret defined');
    }

    // @todo use something stronger than md5.
    return md5($provider->get('shared_secret')->value . $uuid . $provider->uuid());
  }

  /**
   * Get a file URL.
   *
   * @param \Drupal\file\Entity\File $file
   *   File.
   * @param bool $relative
   *   Whether to return an absolute URL or a relative one.
   *
   * @return string
   *   File URL.
   */
  public static function getFileUrl(File $file, $relative = FALSE) {
    return static::createFileUrl($file->getFileUri());
  }

  /**
   * Create a file URL from a uri.
   *
   * This is a wrapper around the file `create_file_url()` function that
   * generates the url in its own render context to prevent an "early rendering"
   * exception caused by the `url.site` cache context being added during the
   * URL generation. This cache metadata - which we don't care about - is not
   * captured when we send our CacheableJsonResponse and to "prevent" cache
   * metadata leak, Drupal throws an exception.
   *
   * @param string $uri
   *   URI.
   * @param bool $relative
   *   Whether to return an absolute URL or a relative one.
   *
   * @return string
   *   File URL.
   *
   * @see \Drupal\file\Entity\File::createFileUrl()
   * @see https://www.drupal.org/node/2513810
   * @see https://www.drupal.org/node/2638686
   */
  public static function createFileUrl($uri, $relative = FALSE) {
    return \Drupal::service('renderer')->executeInRenderContext(new RenderContext(), function () use ($uri, $relative) {
      $url = file_create_url($uri);
      if ($relative && $url) {
        $url = file_url_transform_relative($url);
      }
      return $url;
    });
  }

  /**
   * Format a date or timestamp to a ISO 8601 date.
   *
   * @param int|string $value
   *   Date value.
   *
   * @return string|null
   *   Formatted date or NULL if the date couldn't be formatted.
   */
  public function formatIso8601Date($value) {
    if (empty($value)) {
      return NULL;
    }
    // If the value is numeric, assume it's a timestamp.
    $value = is_numeric($value) ? '@' . $value : $value;
    $date = date_create($value, new \DateTimeZone('UTC'));
    return !empty($date) ? $date->format('c') : NULL;
  }

}
