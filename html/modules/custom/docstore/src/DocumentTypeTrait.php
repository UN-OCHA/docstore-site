<?php

namespace Drupal\docstore;

/**
 * Document type related functions.
 */
trait DocumentTypeTrait {

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
  protected function EndpointExists($endpoint) {
    $document_endpoints = $this->GetEndpoints();
    return isset($document_endpoints[$endpoint]);
  }

  /**
   * Get node type for an endpoint.
   */
  protected function EndpointGetNodeType($endpoint) {
    if (!$this->EndpointExists($endpoint)) {
      throw new \Exception('Endpoint does not exist');
    }

    $document_endpoints = $this->GetEndpoints();
    return $document_endpoints[$endpoint];
  }

  /**
   * Get a list of endpoints and associated node type.
   */
  protected function GetEndpoints() {
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
   * Name of the key.
   */
  protected function getEndpointStateKey() {
    return 'document_endpoints';
  }
}
