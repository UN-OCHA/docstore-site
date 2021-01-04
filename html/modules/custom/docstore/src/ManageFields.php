<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user_bundle\Entity\TypedUser;

/**
 * Provides helper methods for parsing query parameters.
 */
class ManageFields {

  use DocumentTypeTrait;

  /**
   * The provider.
   *
   * @var \Drupal\user_bundle\Entity\TypedUser
   */
  protected $provider;

  /**
   * The node type.
   *
   * @var string
   */
  protected $nodeType;

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
  public function __construct(TypedUser $provider,
    $nodeType,
    EntityFieldManagerInterface $entityFieldManager,
    Connection $database = NULL
    ) {
    $this->provider = $provider;
    $this->nodeType = $nodeType;
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
      'node_reference' => 'string',
      'term_reference' => 'string',
      'email' => 'string',
      'timestamp' => 'date',
      'integer' => 'integer',
      'string_long' => 'text',
      'geofield' => 'geofield',
    ];
  }

  /**
   * Generate a unique machine name.
   */
  protected function generateUniqueMachineName($label, $entity_type, $allow_reuse = TRUE) {
    $label = strtolower($label);
    $label = preg_replace('/[^a-z0-9_]+/', '_', $label);
    $label = preg_replace('/_+/', '_', $label);
    $label = $this->provider->get('prefix')->value . $label;

    $label = trim(substr($label, 0, 31), '_');
    $counter = 0;
    $machine_name = $label;

    // Field can be used on multiple bundles.
    if ($allow_reuse) {
      return $machine_name;
    }

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
  public function addDocumentFieldToIndex($field_name, $field_type, $label) {
    $field_type_mapping = $this->allowedFieldTypes();

    // Skip unknown field types.
    if (!isset($field_type_mapping[$field_type])) {
      return;
    }

    $index = Index::load('documents');

    // Skip existing fields.
    if ($index->getField($field_name)) {
      return;
    }

    $field = new Field($index, $field_name);
    $field->setType($field_type_mapping[$field_type]);
    $field->setPropertyPath($field_name);
    $field->setDatasourceId('entity:node');
    $field->setLabel($label);
    $index->addField($field);

    // Add node title if needed.
    if ($field_type === 'node_reference') {
      $field = new Field($index, $field_name . '_label');
      $field->setType('string');
      $field->setPropertyPath($field_name . ':entity:title');
      $field->setDatasourceId('entity:node');
      $field->setLabel($label . ' (title)');
      $index->addField($field);
    }

    // Add term name if needed.
    if ($field_type === 'term_reference') {
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

    // If target is specified, type defaults to term_reference.
    if (isset($params['target']) && empty($params['type'])) {
      $params['type'] = 'term_reference';
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

    // Map old types.
    if (in_array($params['type'], ['entity_reference', 'entity_reference_uuid'])) {
      $params['type'] = 'term_reference';
    }

    // Reference fields need a target.
    if (in_array($params['type'], ['term_reference', 'node_reference'])) {
      if (empty($params['target'])) {
        throw new \Exception('Target is required for reference fields');
      }

      // Make sure bundle is valid.
      if ($params['type'] === 'node_reference') {
        $node_types = _docstore_get_defined_node_types();
        if (!in_array($params['target'], $node_types)) {
          throw new \Exception(strtr('Target document @target does not exist or is invalid', [
            '@target' => $params['target'],
          ]));
        }

        if (!$this->documentCanBeReferenced($params['target'])) {
          throw new \Exception(strtr('Target document @target does not exist or is invalid', [
            '@target' => $params['target'],
          ]));
        }
      }
      else {
        if (!$this->vocabularyCanBeReferenced($params['target'])) {
          throw new \Exception(strtr('Target vocabulary @target does not exist or is invalid', [
            '@target' => $params['target'],
          ]));
        }
      }
    }
  }

  /**
   * Check is a vocabulary can be referenced.
   */
  protected function vocabularyCanBeReferenced($machine_name) {
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

    // Allow acces to own vocabulary.
    if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') === $this->provider->uuid()) {
      return TRUE;
    }

    // Disallow access to vocabulary of an other provider.
    if (!$vocabulary->getThirdPartySetting('docstore', 'shared')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Check is a node type can be referenced.
   */
  protected function documentCanBeReferenced($type) {
    $node_type = NodeType::load($type);
    if (!$node_type) {
      return FALSE;
    }

    // Allow acces to own document type.
    if ($node_type->getThirdPartySetting('docstore', 'provider_uuid') === $this->provider->uuid()) {
      return TRUE;
    }

    // Disallow access to vocabulary of an other provider.
    if (!$node_type->getThirdPartySetting('docstore', 'shared')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Create document type.
   *
   * @param array $params
   *   Label and author.
   *
   * @return \Drupal\node\Entity\NodeType
   *   Newly created document type.
   */
  public function createDocumentType(array $params) {
    // Check required fields.
    if (empty($params['label'])) {
      throw new \Exception('Label is required');
    }

    if (empty($params['author'])) {
      throw new \Exception('Author is required');
    }

    if (empty($params['endpoint'])) {
      throw new \Exception('Endpoint is required');
    }

    // Create node type.
    if (isset($params['machine_name'])) {
      $machine_name = $params['machine_name'];
    }
    else {
      $machine_name = $this->generateUniqueMachineName($params['label'], 'node_type');
    }

    if (!$this->validEndpoint($params['endpoint'])) {
      throw new \Exception('Endpoint is not allowed');
    }

    if ($this->endpointExists($params['endpoint'])) {
      throw new \Exception('Endpoint is already defined');
    }

    $node_type = NodeType::create([
      'type' => $machine_name,
      'name' => $params['label'],
    ]);

    // Mark document type as shared.
    $node_type->setThirdPartySetting('docstore', 'shared', $params['shared'] ?? FALSE);
    $node_type->setThirdPartySetting('docstore', 'private', !$node_type->getThirdPartySetting('docstore', 'shared'));

    // Can other providers add content.
    $node_type->setThirdPartySetting('docstore', 'content_allowed', $params['content_allowed'] ?? FALSE);

    // Can other providers add fields.
    $node_type->setThirdPartySetting('docstore', 'fields_allowed', $params['fields_allowed'] ?? FALSE);

    // Set base information.
    $node_type->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
    $node_type->setThirdPartySetting('docstore', 'author', $params['author']);
    $node_type->setThirdPartySetting('docstore', 'allow_duplicates', $params['allow_duplicates'] ?? TRUE);
    $node_type->setThirdPartySetting('docstore', 'endpoint', $params['endpoint']);

    $node_type->save();

    // Add files field.
    $this->createDocumentBaseFieldFiles($machine_name);

    // Add author.
    $this->createDocumentBaseFieldAuthor($machine_name);

    // Add private.
    $this->createDocumentBaseFieldPrivate($machine_name);

    docstore_notify_webhooks('document_type:create', $machine_name);

    return $node_type;
  }

  /**
   * Update document type.
   *
   * @param string $type
   *   Label and author.
   * @param array $params
   *   Label and author.
   *
   * @return \Drupal\node\Entity\NodeType
   *   Newly created document type.
   */
  public function updateDocumentType($type, array $params) {
    if (isset($params['author'])) {
      throw new \Exception('Author can not be changed');
    }

    if (isset($params['endpoint'])) {
      throw new \Exception('Endpoint can not be changed');
    }

    if (isset($params['machine_name'])) {
      throw new \Exception('Machine name can not be changed');
    }

    $node_type = NodeType::load($type);

    if (!$node_type) {
      throw new \Exception('Unknown type');
    }

    // Update name/label.
    if (isset($params['label'])) {
      $node_type->set('name', $params['label']);
    }

    // Mark document type as shared.
    $node_type->setThirdPartySetting('docstore', 'shared', $params['shared'] ?? FALSE);
    $node_type->setThirdPartySetting('docstore', 'private', !$node_type->getThirdPartySetting('docstore', 'shared'));

    // Can other providers add content.
    $node_type->setThirdPartySetting('docstore', 'content_allowed', $params['content_allowed'] ?? FALSE);

    // Can other providers add fields.
    $node_type->setThirdPartySetting('docstore', 'fields_allowed', $params['fields_allowed'] ?? FALSE);

    // Set base information.
    $node_type->setThirdPartySetting('docstore', 'allow_duplicates', $params['allow_duplicates'] ?? TRUE);

    $node_type->save();

    docstore_notify_webhooks('document_type:update', $type);

    return $node_type;
  }

  /**
   * Get document fields.
   */
  public function getDocumentFields() {
    $map = $this->entityFieldManager->getFieldDefinitions('node', $this->nodeType);
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

    // Allow acces to own or shared document type.
    $node_type = NodeType::load($this->nodeType);
    if ($node_type->getThirdPartySetting('docstore', 'provider_uuid') !== $this->provider->uuid()) {
      if (!$node_type->getThirdPartySetting('docstore', 'fields_allowed')) {
        throw new \Exception('You cannot add fields to this document type');
      }
    }

    // Set defaults.
    $params['multiple'] = $params['multiple'] ?? FALSE;
    $params['required'] = $params['required'] ?? FALSE;
    $params['machine_name'] = $params['machine_name'] ?? FALSE;
    $params['private'] = $params['private'] ?? FALSE;

    // Create field.
    // @todo pass params?
    if (in_array($params['type'], ['node_reference', 'term_reference'])) {
      return $this->createDocumentReferenceField($params['author'], $params['label'], $params['machine_name'], $params['type'], $params['target'], $params['multiple'], $params['required'], $params['private']);
    }
    else {
      return $this->createDocumentField($params['author'], $params['label'], $params['machine_name'], $params['type'], $params['multiple'], $params['required'], $params['private']);
    }
  }

  /**
   * Create basic document field.
   */
  protected function createDocumentField($author, $label, $machine_name, $field_type, $multiple, $required, $private) {
    $new_field = FALSE;

    // Create new machine name if needed.
    $field_name = $machine_name;
    if (empty($field_name)) {
      $field_name = $this->generateUniqueMachineName($label, 'node');
    }

    // Create storage if needed.
    $field_storage = FieldStorageConfig::loadByName('node', $field_name);

    if (!$field_storage) {
      $new_field = TRUE;

      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $field_type,
        'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
      ]);

      $field_storage->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
      $field_storage->setThirdPartySetting('docstore', 'author', $author);
      $field_storage->save();
    }

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->nodeType,
      'required' => $required,
      'label' => $label,
    ]);

    $field_config->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
    $field_config->setThirdPartySetting('docstore', 'author', $author);
    $field_config->setThirdPartySetting('docstore', 'private', $private);
    $field_config->save();

    // Add to search index display.
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');

    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $view_display */
    $view_display = $storage->load('node.' . $this->nodeType . '.search_index');

    if (!$view_display) {
      $view_display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $this->nodeType,
        'mode' => 'search_index',
        'status' => TRUE,
      ]);
    }

    // Make sure it's active.
    if (!$view_display->status()) {
      $view_display->setStatus(TRUE);
    }

    $view_display->setComponent($field_name, [
      'type' => 'number_unformatted',
      'settings' => [],
    ])->save();

    // Add to index.
    if ($new_field) {
      $this->addDocumentFieldToIndex($field_name, $field_type, $label);
    }

    docstore_notify_webhooks('field:document:create', $field_name);
    return $field_name;
  }

  /**
   * Create reference document field.
   */
  protected function createDocumentReferenceField($author, $label, $machine_name, $type, $bundle, $multiple, $required, $private) {
    $new_field = FALSE;
    $field_type = 'entity_reference_uuid';

    // Create new machine name if needed.
    $field_name = $machine_name;
    if (empty($field_name)) {
      $field_name = $this->generateUniqueMachineName($label, 'node');
    }

    // Target type.
    $target_type = 'taxonomy_term';
    if ($type === 'node_reference') {
      $target_type = 'node';
    }

    // Create storage if needed.
    $field_storage = FieldStorageConfig::loadByName('node', $field_name);

    if (!$field_storage) {
      $new_field = TRUE;

      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $field_type,
        'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
        'settings' => [
          'target_type' => $target_type,
        ],
      ]);

      $field_storage->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
      $field_storage->setThirdPartySetting('docstore', 'author', $author);
      $field_storage->save();
    }

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->nodeType,
      'label' => $label,
      'required' => $required,
      'settings' => [
        'handler' => 'default:' . $target_type,
        'handler_settings' => [
          'target_bundles' => [
            $bundle => $bundle,
          ],
        ],
      ],
    ]);

    $field_config->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
    $field_config->setThirdPartySetting('docstore', 'author', $author);
    $field_config->setThirdPartySetting('docstore', 'private', $private);
    $field_config->save();

    // Add to search index display.
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');

    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $view_display */
    $view_display = $storage->load('node.' . $this->nodeType . '.search_index');
    if (!$view_display) {
      $view_display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $this->nodeType,
        'mode' => 'search_index',
        'status' => TRUE,
      ]);
    }

    // Make sure it's active.
    if (!$view_display->status()) {
      $view_display->setStatus(TRUE);
    }

    $view_display->setComponent($field_name, [
      'type' => 'entity_reference_label',
      'settings' => [],
    ])->save();

    // Add to index.
    if ($new_field) {
      $this->addDocumentFieldToIndex($field_name, $type, $label);
    }

    docstore_notify_webhooks('field:document:create', $field_name);
    return $field_name;
  }

  /**
   * Get document field.
   */
  public function getDocumentField($field_name) {
    $field_config = FieldConfig::loadByName('node', $this->nodeType, $field_name);
    if (!$field_config) {
      throw new \Exception('Field does not exist');
    }

    return [
      'name' => $field_config->getName(),
      'label' => $field_config->getLabel(),
      'description' => $field_config->getDescription(),
      'type' => $field_config->getType(),
      'required' => $field_config->isRequired(),
      'multiple' => $field_config->getFieldStorageDefinition()->isMultiple(),
      'private' => $field_config->getThirdPartySetting('docstore', 'private'),
    ];
  }

  /**
   * Update document field.
   */
  public function updateDocumentField($field_name, $params) {
    $field_config = FieldConfig::loadByName('node', $this->nodeType, $field_name);
    if (!$field_config) {
      throw new \Exception('Field does not exist');
    }

    if (isset($params['label'])) {
      $field_config->setLabel($params['label']);
    }

    if (isset($params['description'])) {
      $field_config->setDescription($params['description']);
    }

    if (isset($params['private'])) {
      $field_config->setThirdPartySetting('docstore', 'private', $params['private']);
    }

    docstore_notify_webhooks('field:document:update', $field_name);
    $field_config->save();
  }

  /**
   * Delete document field.
   *
   * Delete field on all content types.
   */
  public function deleteDocumentField($field_name) {
    $field_storage = FieldStorageConfig::loadByName('node', $field_name);
    if (!$field_storage) {
      throw new \Exception('Field does not exist');
    }

    docstore_notify_webhooks('field:document:delete', $field_name);
    $field_storage->delete();
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
    if (isset($params['machine_name'])) {
      $machine_name = $params['machine_name'];
    }
    else {
      $machine_name = $this->generateUniqueMachineName($params['label'], 'taxonomy_vocabulary');
    }

    $vocabulary = Vocabulary::create([
      'vid' => $machine_name,
      'machine_name' => $machine_name,
      'name' => $params['label'],
    ]);

    // Mark vocabulary as shared.
    $vocabulary->setThirdPartySetting('docstore', 'shared', $params['shared'] ?? TRUE);
    $vocabulary->setThirdPartySetting('docstore', 'private', !$vocabulary->getThirdPartySetting('docstore', 'shared'));

    // Can other providers add content.
    $vocabulary->setThirdPartySetting('docstore', 'content_allowed', $params['content_allowed'] ?? TRUE);

    // Can other providers add fields.
    $vocabulary->setThirdPartySetting('docstore', 'fields_allowed', $params['fields_allowed'] ?? TRUE);

    // Set base information.
    $vocabulary->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
    $vocabulary->setThirdPartySetting('docstore', 'author', $params['author']);
    $vocabulary->setThirdPartySetting('docstore', 'allow_duplicates', $params['allow_duplicates'] ?? TRUE);
    $vocabulary->save();

    // Add created field.
    $this->createVocabularyBaseFieldCreated($machine_name);

    // Add provider uuid.
    $this->createVocabularyBaseFieldProviderUuid($machine_name);

    // Add author HID.
    $this->createVocabularyBaseFieldHidId($machine_name);

    docstore_notify_webhooks('vocabulary:create', $machine_name);
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
      'author',
      'provider_uuid',
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
    if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $this->provider->uuid()) {
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
    if (isset($params['allow_duplicates']) && $params['allow_duplicates'] === FALSE && $vocabulary->getThirdPartySetting('docstore', 'allow_duplicates') === TRUE) {
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

      $vocabulary->setThirdPartySetting('docstore', 'allow_duplicates', $params['allow_duplicates'] ?? TRUE);
      unset($params['allow_duplicates']);
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

    docstore_notify_webhooks('vocabulary:update', $vocabulary->id());
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
    if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $this->provider->uuid()) {
      throw new \Exception('Vocabulary is not owned by you');
    }

    docstore_notify_webhooks('vocabulary:delete', $vocabulary->id());
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

    // No reference to nodes allowed.
    if ($params['type'] === 'node_reference') {
      throw new \Exception('You cannot reference a document from a term');
    }

    // Allow acces to own or shared document type.
    if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $this->provider->uuid()) {
      if (!$vocabulary->getThirdPartySetting('docstore', 'fields_allowed')) {
        throw new \Exception('You cannot add fields to this vocabulary');
      }
    }

    // @todo Force Id and code fields to be strings.
    // Set defaults.
    $params['multiple'] = $params['multiple'] ?? FALSE;
    $params['required'] = $params['required'] ?? FALSE;
    $params['private'] = $params['private'] ?? FALSE;
    $params['machine_name'] = $params['machine_name'] ?? FALSE;

    // Create field.
    if ($params['type'] === 'term_reference') {
      $field_name = $this->createVocabularyReferenceField($params['author'], $vocabulary->id(), $params['label'], $params['machine_name'], $params['target'], $params['multiple'], $params['required'], $params['private']);
    }
    else {
      $field_name = $this->createVocabularyField($params['author'], $vocabulary->id(), $params['label'], $params['machine_name'], $params['type'], $params['multiple'], $params['required'], $params['private']);
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
    $field_config = FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), $field_name);
    if (!$field_config) {
      throw new \Exception('Field does not exist');
    }

    return [
      'name' => $field_config->getName(),
      'label' => $field_config->getLabel(),
      'description' => $field_config->getDescription(),
      'type' => $field_config->getType(),
      'multiple' => $field_config->getFieldStorageDefinition()->isMultiple(),
      'private' => $field_config->getThirdPartySetting('docstore', 'private'),
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
    $field_config = FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), $field_name);
    if (!$field_config) {
      throw new \Exception('Field does not exist');
    }

    if (isset($params['type'])) {
      throw new \Exception('Type can not be changed');
    }

    if (isset($params['label'])) {
      $field_config->setLabel($params['label']);
    }

    if (isset($params['description'])) {
      $field_config->setDescription($params['description']);
    }

    if (isset($params['private'])) {
      $field_config->setThirdPartySetting('docstore', 'private', $params['private']);
    }

    docstore_notify_webhooks('field:vocabulary:update', $field_name);
    $field_config->save();
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
    $field_config = FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), $field_name);
    if (!$field_config) {
      throw new \Exception('Field does not exist');
    }

    docstore_notify_webhooks('field:vocabulary:delete', $field_name);
    $field_config->delete();
  }

  /**
   * Create a vocabulary field for a provider.
   */
  protected function createVocabularyField($author, $bundle, $label, $machine_name, $field_type, $multiple, $required, $private) {
    $field_name = $machine_name;
    if (empty($field_name)) {
      $provider_prefix = $bundle . '_';
      $field_name = $this->generateUniqueMachineName($label, 'taxonomy_term', $provider_prefix);
    }

    // Create storage.
    $field_storage = FieldStorageConfig::load('taxonomy_term.' . $field_name);
    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'taxonomy_term',
        'type' => $field_type,
        'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
      ]);

      $field_storage->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
      $field_storage->setThirdPartySetting('docstore', 'author', $author);
      $field_storage->save();
    }

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
      'required' => $required,
    ]);

    $field_config->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
    $field_config->setThirdPartySetting('docstore', 'author', $author);
    $field_config->setThirdPartySetting('docstore', 'private', $private);
    $field_config->save();

    docstore_notify_webhooks('field:vocabulary:create', $field_name);
    return $field_name;
  }

  /**
   * Create a reference field on a vocabulary for a provider.
   */
  protected function createVocabularyReferenceField($author, $bundle, $label, $machine_name, $target, $multiple, $required, $private) {
    $field_type = 'entity_reference_uuid';

    $field_name = $machine_name;
    if (empty($field_name)) {
      $provider_prefix = $bundle . '_';
      $field_name = $this->generateUniqueMachineName($label, 'taxonomy_term', $provider_prefix);
    }

    // Create storage.
    $field_storage = FieldStorageConfig::load('taxonomy_term.' . $field_name);
    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'taxonomy_term',
        'type' => $field_type,
        'cardinality' => $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1,
        'settings' => [
          'target_type' => 'taxonomy_term',
        ],
      ]);

      $field_storage->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
      $field_storage->setThirdPartySetting('docstore', 'author', $author);
      $field_storage->save();
    }

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
      'required' => $required,
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            $target => $target,
          ],
        ],
      ],
    ]);

    $field_config->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
    $field_config->setThirdPartySetting('docstore', 'author', $author);
    $field_config->setThirdPartySetting('docstore', 'private', $private);
    $field_config->save();

    docstore_notify_webhooks('field:vocabulary:create', $field_name);
    return $field_name;
  }

  /**
   * Add created field to a vocabulary.
   *
   * @param string $bundle
   *   Vocabulary bundle.
   */
  public function createVocabularyBaseFieldCreated(string $bundle) {
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
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ]);

    $field_config->setThirdPartySetting('docstore', 'private', FALSE);
    $field_config->save();
  }

  /**
   * Add provider uuid field to a vocabulary.
   *
   * @param string $bundle
   *   Vocabulary bundle.
   */
  public function createVocabularyBaseFieldProviderUuid(string $bundle) {
    $label = 'Provider UUID';
    $field_name = 'provider_uuid';
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
    $field_config = FieldConfig::create([
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
    ]);

    $field_config->setThirdPartySetting('docstore', 'private', FALSE);
    $field_config->save();
  }

  /**
   * Add HID id field to a vocabulary.
   *
   * @param string $bundle
   *   Vocabulary bundle.
   */
  public function createVocabularyBaseFieldHidId(string $bundle) {
    $label = 'Author';
    $field_name = 'author';
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
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ]);

    $field_config->setThirdPartySetting('docstore', 'private', FALSE);
    $field_config->save();
  }

  /**
   * Add author field to a vocabulary.
   *
   * @param string $bundle
   *   Vocabulary bundle.
   */
  public function createDocumentBaseFieldAuthor(string $bundle) {
    $label = 'Author';
    $field_name = 'author';
    $field_type = 'string';

    $field_storage = FieldStorageConfig::load('node.' . $field_name);
    if (!$field_storage) {
      // Create storage.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $field_type,
        'cardinality' => 1,
      ]);
      $field_storage->save();
    }

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ]);

    $field_config->setThirdPartySetting('docstore', 'private', FALSE);
    $field_config->save();
  }

  /**
   * Add private field to a document.
   *
   * @param string $bundle
   *   Document bundle.
   */
  public function createDocumentBaseFieldPrivate(string $bundle) {
    $label = 'Private';
    $field_name = 'private';
    $field_type = 'boolean';

    $field_storage = FieldStorageConfig::load('node.' . $field_name);
    if (!$field_storage) {
      // Create storage.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $field_type,
        'cardinality' => 1,
      ]);
      $field_storage->save();
    }

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ]);

    $field_config->setThirdPartySetting('docstore', 'private', FALSE);
    $field_config->save();
  }

  /**
   * Add files field to a document type.
   *
   * @param string $bundle
   *   Node type.
   */
  public function createDocumentBaseFieldFiles(string $bundle) {
    $label = 'Files';
    $field_name = 'files';
    $field_type = 'entity_reference_uuid';

    $field_storage = FieldStorageConfig::load('node.' . $field_name);
    if (!$field_storage) {
      // Create storage.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $field_type,
        'cardinality' => -1,
        'settings' => [
          'target_type' => 'media',
        ],
      ]);
      $field_storage->save();
    }

    // Create instance.
    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
      'settings' => [
        'handler' => 'default:user',
        'handler_settings' => [
          'target_bundles' => [
            'file' => 'file',
          ],
        ],
      ],
    ]);

    $field_config->setThirdPartySetting('docstore', 'private', FALSE);
    $field_config->save();
  }

}
