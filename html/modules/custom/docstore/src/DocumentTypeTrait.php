<?php

namespace Drupal\docstore;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Document type related functions.
 */
trait DocumentTypeTrait {

  /**
   * Name of the key.
   */
  protected function getEndpointStateKey() {
    return 'document_endpoints';
  }

  /**
   * Name of the key.
   */
  protected function getDocumentTypeStateKey() {
    return 'document_document_types';
  }

  /**
   * Check if an endpoint is valid.
   */
  protected function validEndpoint($endpoint) {
    $illegal_endpoints = [
      'wait',
      'me',
      'webhooks',
      'types',
      'fields',
      'vocabularies',
      'terms',
      'media',
      'files',
      'any',
      'all',
    ];

    // Check for known endpoints.
    if (in_array($endpoint, $illegal_endpoints)) {
      return FALSE;
    }

    // Check for illegal characters.
    if (preg_match('/[^a-z-]/', $endpoint)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Check if an endpoint exists.
   */
  protected function endpointExists($endpoint) {
    $document_endpoints = $this->GetEndpoints();
    return isset($document_endpoints[$endpoint]);
  }

  /**
   * Get node type for an endpoint.
   *
   * @param string $endpoint
   *   The document (node) type endpoint.
   *
   * @return string
   *   The node type ID.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   404 Not Found if there is no matching node type.
   */
  protected function endpointGetNodeType($endpoint) {
    if (!$this->EndpointExists($endpoint)) {
      throw new NotFoundHttpException('Endpoint does not exist');
    }

    $document_endpoints = $this->getEndpoints();
    return $document_endpoints[$endpoint];
  }

  /**
   * Get a list of endpoints and associated node type.
   */
  protected function getEndpoints() {
    $document_endpoints = \Drupal::state()->get($this->getEndpointStateKey(), []);
    if (empty($document_endpoints) && isset($this->entityTypeManager)) {
      $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
      foreach ($node_types as $node_type) {
        $document_endpoints[$node_type->getThirdPartySetting('docstore', 'endpoint')] = $node_type->id();
      }

      \Drupal::state()->set($this->getEndpointStateKey(), $document_endpoints);
    }

    return $document_endpoints;
  }

  /**
   * Rebuild endpoints state.
   */
  protected function rebuildEndpoints() {
    \Drupal::state()->delete($this->getEndpointStateKey());
    $this->GetEndpoints();
  }

  /**
   * Get the endpoint type allowed for the given resource type.
   *
   * @param string $type
   *   Resource type (currently a node type).
   * @param string $mode
   *   The operation (read or something else).
   *
   * @return string
   *   Either "Any" or the endpoint matching the resource type.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   404 Not Found if there is no matching node type.
   *
   * @todo review the logic of this.
   */
  protected function typeAllowed($type, $mode = 'read') {
    // Allow read operations on "any" endpoint.
    if ($type === 'any' && $mode === 'read') {
      return 'any';
    }

    // Allow read operations on "all" endpoint.
    if ($type === 'all' && $mode === 'read') {
      return 'any';
    }

    return $this->EndpointGetNodeType($type);
  }

  /**
   * List of accessible document types by provider.
   */
  protected function getAccessibleDocumentTypes($provider) {
    $document_types = \Drupal::state()->get($this->getDocumentTypeStateKey(), []);
    if (empty($document_types) || !isset($document_types[$provider->id()])) {
      if (isset($this->entityTypeManager)) {
        $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
        foreach ($node_types as $node_type) {
          if ($this->providerCanRead($node_type->id(), $provider)) {
            $document_types[$provider->id()][] = $node_type->id();
          }
        }

        \Drupal::state()->set($this->getDocumentTypeStateKey(), $document_types);
      }
      else {
        return [];
      }
    }

    return $document_types[$provider->id()];
  }

  /**
   * Rebuild endpoints state.
   */
  protected function rebuildDocumentTypes($provider = NULL) {
    \Drupal::state()->delete($this->getDocumentTypeStateKey());

    if ($provider) {
      $this->getAccessibleDocumentTypes($provider);
    }
  }

  /**
   * Get a node type entity.
   *
   * Note: this assumes that the class using that trait as a `entityTypeManager`
   * member variable.
   *
   * @param string $type
   *   Node type.
   *
   * @return \Drupal\node\Entity\NodeType
   *   Node type entity.
   *
   * @throw \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   404 Not Found if the node type was not found.
   */
  protected function getNodeType($type) {
    /** @var \Drupal\node\Entity\NodeType $type */
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);

    if (!$node_type) {
      throw new NotFoundHttpException('Document type not found.');
    }

    return $node_type;
  }

  /**
   * Get a node type label.
   *
   * @param string $type
   *   Node type.
   *
   * @return string
   *   Node type label.
   */
  protected function getNodeTypeLabel($type) {
    return $this->getNodeType($type)->label();
  }

}
