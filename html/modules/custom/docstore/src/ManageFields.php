<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides helper methods for parsing query parameters.
 */
class ManageFields {

  /**
   * The provider.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $provider;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $provider,
    EntityFieldManagerInterface $entityFieldManager,
    Connection $database
    ) {
    $this->provider = $provider;
    $this->entityFieldManager = $entityFieldManager;
    $this->database = $database;
  }

  /**
   * Allowed field types and mapping to search API.
   */
  public function allowedFieldTypes() {
    return [
      'boolean' => 'boolean',
      'string' => 'string',
      'entity_reference' => 'integer',
      'entity_reference_uuid' => 'string',
      'email' => 'string',
      'timestamp' => 'date',
      'integer' => 'integer',
      'string_long' => 'text',
    ];
  }

  /**
   * Generate a unique machine name.
   */
  protected function generateUniqueMachineName($label, $entity_type) {
    $label = strtolower($label);
    $label = preg_replace('/[^a-z0-9_]+/', '_', $label);
    $label = preg_replace('/_+/', '_', $label);
    $label = $this->provider->get('prefix')->value . $label;

    $label = trim(substr($label, 0, 31), '_');
    $counter = 0;
    $machine_name = $label;
    while ($this->machineNameExists($machine_name, $entity_type)) {
      $suffix = '_' . $counter++;
      $machine_name = substr($label, 0, 31 - strlen($suffix)) . $suffix;
    }
    return $machine_name;
  }

  /**
   * Check if a machine name already exists.
   */
  protected function machineNameExists($machine_name, $entity_type) {
    if ($entity_type == 'taxonomy_vocabulary') {
      $vocabulary = Vocabulary::load($machine_name);
      return !empty($vocabulary);
    }

    $field = FieldStorageConfig::loadByName($entity_type, $machine_name);

    return !empty($field);
  }

  /**
   * Add field to index.
   */
  protected function addDocumentFieldToIndex($field_name, $field_type, $label) {
    $field_type_mapping = $this->allowedFieldTypes();

    // Skip unknown field types.
    if (!isset($field_type_mapping[$field_type])) {
      return;
    }

    $index = Index::load('documents');

    $field = new Field($index, $field_name);
    $field->setType($field_type_mapping[$field_type]);
    $field->setPropertyPath($field_name);
    $field->setDatasourceId('entity:node');
    $field->setLabel($label);
    $index->addField($field);

    // Add term name if needed.
    if ($field_type == 'entity_reference' || $field_type == 'entity_reference_uuid') {
      $field = new Field($index, $field_name . '_label');
      $field->setType('string');
      $field->setPropertyPath($field_name . ':entity:name');
      $field->setDatasourceId('entity:node');
      $field->setLabel($label . ' (name)');
      $index->addField($field);
    }

    // Save.
    $index->save();

    // Re-index.
    $index->reindex();
  }

  /**
   * Check field parameters.
   */
  protected function validFieldParameters(&$params) {
    // Multi value field.
    if (!isset($params['multiple'])) {
      $params['multiple'] = FALSE;
    }

    // Check required fields.
    if (empty($params['label'])) {
      throw new \Exception('Label is required');
    }

    if (empty($params['author'])) {
      throw new \Exception('Author is required');
    }

    // If target is specified, type is not needed.
    if (isset($params['target'])) {
      $params['type'] = 'entity_reference_uuid';
    }
    else {
      if (empty($params['type'])) {
        throw new \Exception('Type is required');
      }

      $allowed_types = $this->allowedFieldTypes();
      if (!isset($allowed_types[$params['type']])) {
        throw new \Exception('Unknown type');
      }
    }

    // Reference fields need a target.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      if (empty($params['target'])) {
        throw new \Exception('Target is required for reference fields');
      }

      // Make sure bundle is valid.
      if (!$this->vocabularyIsValid($params['target'])) {
        throw new \Exception('Target does not exist or is invalid');
      }
    }
  }

  /**
   * Check is a vocabulary does exist.
   */
  protected function vocabularyIsValid($machine_name) {
    if (Uuid::isValid($machine_name)) {
      $vocabulary = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_vocabulary', $machine_name);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = Vocabulary::load($machine_name);
      if (!$vocabulary) {
        return FALSE;
      }
    }

    // Disallow access to base vocabularies.
    if (strpos($machine_name, 'base_') === 0) {
      return FALSE;
    }

    // Disallow access to vocabulary of an other provider.
    if (strpos($machine_name, 'shared_') !== 0 && strpos($machine_name, $this->provider->get('prefix')->value) !== 0) {
      return FALSE;
    }

    // Check name.
    return $this->machineNameExists($machine_name, 'taxonomy_vocabulary');
  }

  /**
   * Get document fields.
   */
  public function getDocumentFields() {
    $map = $this->entityFieldManager->getFieldDefinitions('node', 'document');
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

    return $data;
  }

  /**
   * Add document field.
   */
  public function addDocumentField($params) {
    $this->validFieldParameters($params);

    // Check field types.
    $allowed_types = $this->allowedFieldTypes();
    if (!isset($allowed_types[$params['type']])) {
      throw new \Exception('Field type does not exist');
    }

    // Create field.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      return $this->createDocumentReferenceField($params['author'], $params['label'], $params['target'], $params['multiple']);
    }
    else {
      return $this->createDocumentField($params['author'], $params['label'], $params['type'], $params['multiple']);
    }
  }

  /**
   * Create basic document field.
   */
  protected function createDocumentField($author, $label, $field_type, $multiple = FALSE) {
    // Create new machine name.
    $field_name = $this->generateUniqueMachineName($label, 'node');

    // Create storage.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $field_type,
      'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
    ]);

    $field_storage->setThirdPartySetting('docstore', 'base_provider_uuid', $this->provider->uuid());
    $field_storage->setThirdPartySetting('docstore', 'base_author_hid', $author);
    $field_storage->save();

    // Create instance.
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'document',
      'label' => $label,
    ])->save();

    // Add to index.
    $this->addDocumentFieldToIndex($field_name, $field_type, $label);

    return $field_name;
  }

  /**
   * Create reference document field.
   */
  protected function createDocumentReferenceField($author, $label, $bundle, $multiple = FALSE) {
    $field_type = 'entity_reference_uuid';
    $field_name = $this->generateUniqueMachineName($label, 'node');

    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $field_type,
      'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);

    $field_storage->setThirdPartySetting('docstore', 'base_provider_uuid', $this->provider->uuid());
    $field_storage->setThirdPartySetting('docstore', 'base_author_hid', $author);
    $field_storage->save();

    // Create instance.
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'document',
      'label' => $label,
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            $bundle => $bundle,
          ],
        ],
      ],
    ])->save();

    // Add to index.
    $this->addDocumentFieldToIndex($field_name, $field_type, $label);

    return $field_name;
  }

  /**
   * Get document field.
   */
  public function getDocumentField($field_name) {
    $field = FieldConfig::loadByName('node', 'document', $field_name);
    if (!$field) {
      throw new \Exception('Field does not exist');
    }

    return [
      'name' => $field->getName(),
      'label' => $field->getLabel(),
      'description' => $field->getDescription(),
      'type' => $field->getType(),
      'multiple' => $field->getFieldStorageDefinition()->isMultiple(),
    ];
  }

  /**
   * Update document field.
   */
  public function updateDocumentField($field_name, $params) {
    $field = FieldConfig::loadByName('node', 'document', $field_name);
    if (!$field) {
      throw new \Exception('Field does not exist');
    }

    if (isset($params['label'])) {
      $field->setLabel($params['label']);
    }

    if (isset($params['description'])) {
      $field->setDescription($params['description']);
    }

    $field->save();
  }

  /**
   * Delete document field.
   */
  public function deleteDocumentField($field_name) {
    $field = FieldStorageConfig::loadByName('node', $field_name);
    if (!$field) {
      throw new \Exception('Field does not exist');
    }

    $field->delete();
  }

  /**
   * Create vocabulary.
   *
   * @param array $params
   *   Label and author.
   *
   * @return \Drupal\taxonomy\Entity\Vocabulary
   *   Newly created vocabulary.
   */
  public function createVocabulary(array $params) {
    // Check required fields.
    if (empty($params['label'])) {
      throw new \Exception('Label is required');
    }

    if (empty($params['author'])) {
      throw new \Exception('Author is required');
    }

    // Create vocabulary.
    $machine_name = $this->generateUniqueMachineName($params['label'], 'taxonomy_vocabulary');

    $vocabulary = Vocabulary::create([
      'vid' => $machine_name,
      'machine_name' => $machine_name,
      'name' => $params['label'],
    ]);

    $vocabulary->setThirdPartySetting('docstore', 'base_provider_uuid', $this->provider->uuid());
    $vocabulary->setThirdPartySetting('docstore', 'base_author_hid', $params['author']);
    $vocabulary->setThirdPartySetting('docstore', 'base_allow_duplicates', $params['allow_duplicates'] ?? TRUE);
    $vocabulary->save();

    // Add created field.
    $this->createVocabularyBaseFieldCreated($machine_name);

    // Add provider uuid.
    $this->createVocabularyBaseFieldProviderUuid($machine_name);

    // Add author HID.
    $this->createVocabularyBaseFieldHidId($machine_name);

    return $vocabulary;
  }

  /**
   * Update vocabulary.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary to update.
   * @param array $params
   *   Label, description.
   * @param string $method
   *   Either PUT or PATCH.
   *
   * @return \Drupal\taxonomy\Entity\Vocabulary
   *   Newly created vocabulary.
   */
  public function updateVocabulary(Vocabulary $vocabulary, array $params, string $method) {
    $protected_fields = [
      'base_author_hid',
      'base_provider_uuid',
      'changed',
      'created',
      'default_langcode',
      'langcode',
      'parent',
      'revision_created',
      'revision_id',
      'revision_log_message',
      'revision_user',
      'status',
      'tid',
      'uuid',
      'vid',
      'vocabulary',
      'weight',
    ];

    // Provider can only update own vocabulary.
    if ($vocabulary->getThirdPartySetting('docstore', 'base_provider_uuid') !== $this->provider->uuid()) {
      throw new \Exception('Vocabulary is not owned by you');
    }

    // Check required fields.
    if ($method === 'PUT') {
      if (empty($params['label'])) {
        throw new \Exception('Label is required');
      }
    }

    // Label is actually name.
    if (isset($params['label'])) {
      $params['name'] = $params['label'];
      unset($params['label']);
    }

    $updated_fields = [];

    // Check allow_duplicate changes.
    if (isset($params['base_allow_duplicates']) && $params['base_allow_duplicates'] === FALSE && $vocabulary->getThirdPartySetting('docstore', 'base_allow_duplicates') === TRUE) {
      // Check all existing terms.
      $query = $this->database->select('taxonomy_term_field_data', 't')
        ->fields('t', [
          'name',
        ]);
      $query->condition('vid', $vocabulary->id());
      $query->condition('status', 1);
      $query->groupBy('vid');
      $query->groupBy('name');
      $query->having('count(tid) > 1');

      $terms = $query->execute()->fetchAll();
      if (!empty($terms)) {
        throw new \Exception('Vocabulary contains duplicate terms');
      }

      $vocabulary->setThirdPartySetting('docstore', 'base_allow_duplicates', $params['allow_duplicates'] ?? TRUE);
      unset($params['base_allow_duplicates']);
    }

    // Update all fields specified in params.
    foreach ($params as $name => $values) {
      // Make sure protected fields aren't set.
      if (isset($protected_fields[$name])) {
        throw new \Exception(strtr('Field @name cannot be changed', ['@name' => $name]));
      }

      if ($name === 'name' || $name === 'description') {
        $vocabulary->set($name, $values);
        $updated_fields[] = $name;
      }
      else {
        throw new \Exception(strtr('Field @name does not exists', ['@name' => $name]));
      }
    }

    // Remove all fields not part of params.
    if ($method === 'PUT') {
      if (!in_array('description', $updated_fields)) {
        $vocabulary->set('description', NULL);
      }
    }

    $vocabulary->save();

    return $vocabulary;
  }

  /**
   * Delete vocabulary.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary to delete.
   */
  public function deleteVocabulary(Vocabulary $vocabulary) {
    // Provider can only update own vocabulary.
    if ($vocabulary->getThirdPartySetting('docstore', 'base_provider_uuid') !== $this->provider->uuid()) {
      throw new \Exception('Vocabulary is not owned by you');
    }

    $vocabulary->delete();
  }

  /**
   * Create vocabulary fields.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param array $params
   *   Parameters to create the field.
   */
  public function addVocabularyField(Vocabulary $vocabulary, array $params) {
    // Check field parameters.
    $this->validFieldParameters($params);

    // Create field.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      $field_name = $this->createVocabularyReferenceField($vocabulary->id(), $params['label'], $params['target'], $params['multiple']);
    }
    else {
      $field_name = $this->createVocabularyField($vocabulary->id(), $params['label'], $params['type'], $params['multiple']);
    }

    return $field_name;
  }

  /**
   * Get vocabulary field.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param string $field_name
   *   Field name.
   */
  public function getVocabularyField(Vocabulary $vocabulary, string $field_name) {
    $field = FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), $field_name);
    if (!$field) {
      throw new \Exception('Field does not exist');
    }

    return [
      'name' => $field->getName(),
      'label' => $field->getLabel(),
      'description' => $field->getDescription(),
      'type' => $field->getType(),
      'multiple' => $field->getFieldStorageDefinition()->isMultiple(),
    ];
  }

  /**
   * Update vocabulary field.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param string $field_name
   *   Field name.
   * @param array $params
   *   Parameters to create the field.
   */
  public function updateVocabularyField(Vocabulary $vocabulary, string $field_name, array $params) {
    $field = FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), $field_name);
    if (!$field) {
      throw new \Exception('Field does not exist');
    }

    if (isset($params['type'])) {
      throw new \Exception('Type can not be changed');
    }

    if (isset($params['label'])) {
      $field->setLabel($params['label']);
    }

    if (isset($params['description'])) {
      $field->setDescription($params['description']);
    }

    $field->save();
  }

  /**
   * Delete vocabulary field.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param string $field_name
   *   Field name.
   */
  public function deleteVocabularyField(Vocabulary $vocabulary, string $field_name) {
    $field = FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), $field_name);
    if (!$field) {
      throw new \Exception('Field does not exist');
    }

    $field->delete();
  }

  /**
   * Create a vocabulary field for a provider.
   */
  protected function createVocabularyField($bundle, $label, $field_type, $multiple = FALSE) {
    $provider_prefix = $bundle . '_';
    $field_name = $this->generateUniqueMachineName($label, 'taxonomy_term', $provider_prefix);

    // Create storage.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'taxonomy_term',
      'type' => $field_type,
      'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
    ]);
    $field_storage->save();

    // Create instance.
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ])->save();

    return $field_name;
  }

  /**
   * Create a reference field on a vocabulary for a provider.
   */
  protected function createVocabularyReferenceField($bundle, $label, $target, $multiple = FALSE) {
    $field_type = 'entity_reference_uuid';

    $provider_prefix = $bundle . '_';
    $field_name = $this->generateUniqueMachineName($label, 'taxonomy_term', $provider_prefix);

    // Create storage.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'taxonomy_term',
      'type' => $field_type,
      'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $field_storage->save();

    // Create instance.
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            $target => $target,
          ],
        ],
      ],
    ])->save();

    return $field_name;
  }

  /**
   * Add created field to a vocabulary.
   *
   * @param string $bundle
   *   Vocabulary bundle.
   */
  protected function createVocabularyBaseFieldCreated(string $bundle) {
    $label = 'Created';
    $field_name = 'created';
    $field_type = 'timestamp';

    $field_storage = FieldStorageConfig::load('taxonomy_term.' . $field_name);
    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'taxonomy_term',
        'type' => $field_type,
        'cardinality' => 1,
      ]);
      $field_storage->save();
    }

    // Create instance.
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

  /**
   * Add provider uuid field to a vocabulary.
   *
   * @param string $bundle
   *   Vocabulary bundle.
   */
  protected function createVocabularyBaseFieldProviderUuid(string $bundle) {
    $label = 'Provider UUID';
    $field_name = 'base_provider_uuid';
    $field_type = 'entity_reference_uuid';

    $field_storage = FieldStorageConfig::load('taxonomy_term.' . $field_name);
    if (!$field_storage) {
      // Create storage.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'taxonomy_term',
        'type' => $field_type,
        'cardinality' => 1,
        'settings' => [
          'target_type' => 'user',
        ],
      ]);
      $field_storage->save();
    }

    // Create instance.
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
      'settings' => [
        'handler' => 'default:user',
        'handler_settings' => [
          'target_bundles' => [
            'provider' => 'provider',
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Add HID id field to a vocabulary.
   *
   * @param string $bundle
   *   Vocabulary bundle.
   */
  protected function createVocabularyBaseFieldHidId(string $bundle) {
    $label = 'Author (HID)';
    $field_name = 'base_author_hid';
    $field_type = 'string';

    $field_storage = FieldStorageConfig::load('taxonomy_term.' . $field_name);
    if (!$field_storage) {
      // Create storage.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'taxonomy_term',
        'type' => $field_type,
        'cardinality' => 1,
      ]);
      $field_storage->save();
    }

    // Create instance.
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

}
