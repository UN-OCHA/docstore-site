<?php

namespace Drupal\docstore;

use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides helper methods for parsing query parameters.
 */
class ManageFields {

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $provider) {
    $this->provider = $provider;
  }

  /**
   * Delete document field.
   */
  public function deleteDocumentField($field_name) {
    $field = FieldStorageConfig::loadByName('node', $field_name);
    $field->delete();
  }

}
