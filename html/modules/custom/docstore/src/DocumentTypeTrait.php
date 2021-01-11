<?php

namespace Drupal\docstore;

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
   */
  protected function endpointGetNodeType($endpoint) {
    if (!$this->EndpointExists($endpoint)) {
      throw new \Exception('Endpoint does not exist');
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
   * Check if provider can read content.
   */
  protected function providerCanRead($node_type, $provider) {
    $type = $this->entityTypeManager->getStorage('node_type')->load($node_type);

    if (!$provider->isAnonymous() && $type->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      return TRUE;
    }

    if ($type->getThirdPartySetting('docstore', 'shared')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if provider can create, update, delete content.
   */
  protected function providerCanCreateUpdateDelete($node_type, $provider) {
    $type = $this->entityTypeManager->getStorage('node_type')->load($node_type);

    if (!$provider->isAnonymous() && $type->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      return TRUE;
    }

    if (!$provider->isAnonymous() && $type->getThirdPartySetting('docstore', 'content_allowed')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * List of accessible document types by provider.
   */
  /**
   * Get a list of endpoints and associated node type.
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

}
