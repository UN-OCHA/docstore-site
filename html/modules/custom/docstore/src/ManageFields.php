<?php

namespace Drupal\docstore;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user_bundle\Entity\TypedUser;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Yaml\Yaml;

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database = NULL
    ) {
    $this->provider = $provider;
    $this->nodeType = $nodeType;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
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
      'datetime' => 'date',
      'daterange' => 'date',
      'integer' => 'integer',
      'string_long' => 'text',
      'geofield' => 'string',
      'link' => 'string',
      'telephone' => 'string',
      'address' => '',
    ];
  }

  /**
   * Generate a unique machine name.
   */
  protected function generateUniqueMachineName($label, $entity_type, $allow_reuse = TRUE) {
    // @phpstan-ignore-next-line
    $prefix = $this->provider->get('prefix')->value;

    $label = strtolower($label);
    $label = preg_replace('/[^a-z0-9_]+/', '_', $label);
    $label = preg_replace('/_+/', '_', $label);
    $label = $prefix . $label;

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
   * Get indexed document fields.
   */
  public function getIndexedDocumentFields() {
    return $this->getIndexedFields('documents_' . $this->nodeType);
  }

  /**
   * Get indexed term fields.
   *
   * @param string $type
   *   Bundle.
   */
  public function getIndexedTermFields($type) {
    return $this->getIndexedFields('terms_' . $type);
  }

  /**
   * Get indexed fields.
   *
   * @param string $index_name
   *   Search API index name.
   */
  public function getIndexedFields($index_name) {
    // Load the search api index.
    $index = Index::load($index_name);
    if (empty($index)) {
      return;
    }

    $fields = $index->getFields(TRUE);
    $result = [];
    foreach ($fields as $key => $field) {
      $result[$key] = [
        'id' => $key,
        'label' => $field->getLabel(),
        'field' => $field->getFieldIdentifier(),
        'datasource' => $field->getDatasourceId(),
        'path' => $field->getPropertyPath(),
        'combined_path' => $field->getCombinedPropertyPath(),
      ];
    }

    return $result;
  }

  /**
   * Add indexed document field.
   */
  public function addIndexedDocumentField($params) {
    // We need the machine name.
    if (!isset($params['machine_name'])) {
      throw new \Exception('Machine name is required');
    }

    // Allow acces to own or shared document type.
    $node_type = NodeType::load($this->nodeType);
    if (!$node_type) {
      throw new \Exception('Document type does not exist');
    }

    if ($node_type->getThirdPartySetting('docstore', 'provider_uuid') !== $this->provider->uuid()) {
      if (!$node_type->getThirdPartySetting('docstore', 'fields_allowed')) {
        throw new \Exception('You cannot add fields to this document type');
      }
    }

    $field_config = FieldConfig::loadByName('node', $this->nodeType, $params['machine_name']);
    if (empty($field_config)) {
      throw new NotFoundHttpException('Field does not exist');
    }

    // Add to search index display.
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');

    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $view_display */
    $view_display = $storage->load('node.' . $this->nodeType . '.search_index');

    if (empty($view_display)) {
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

    $view_display->setComponent($params['machine_name'], [
      'settings' => [],
    ])->save();

    // Add to index.
    $this->addDocumentFieldToIndex($field_config, $field_config->getLabel());
  }

  /**
   * Delete indexed field.
   *
   * @param string $id
   *   Machine name.
   */
  public function deleteIndexedDocumentField($id) {
    return $this->deleteIndexedField('documents_' . $this->nodeType, $id);
  }

  /**
   * Delete indexed field.
   *
   * @param string $index_name
   *   Search API index name.
   * @param string $id
   *   Machine name.
   */
  public function deleteIndexedField($index_name, $id) {
    // Load the search api index.
    $index = Index::load($index_name);
    if (empty($index)) {
      throw new NotFoundHttpException();
    }

    $index->removeField($id);
    $index->save();
  }

  /**
   * Add document field to index.
   *
   * @param \Drupal\field\Entity\FieldConfig $field_config
   *   Field config.
   * @param string $label
   *   Field label.
   */
  public function addDocumentFieldToIndex(FieldConfig $field_config, $label) {
    $this->addFieldToIndex('documents_' . $this->nodeType, 'entity:node', $field_config, $label);
  }

  /**
   * Add document field to index.
   *
   * @param \Drupal\field\Entity\FieldConfig $field_config
   *   Field config.
   * @param string $label
   *   Field label.
   */
  public function addTermFieldToIndex(FieldConfig $field_config, $label) {
    $this->addFieldToIndex('terms_' . $field_config->getTargetBundle(), 'entity:taxonomy_term', $field_config, $label);
  }

  /**
   * Add field to index.
   *
   * Note: additional fields ends with a `_` to avoid clash with the machine
   * names of user created fields. This is indeed a forbidden pattern.
   * We use `_` as suffix and not prefix so that sorting a list of fields by
   * field names will ensure the extra fields appear after the base fields,
   * easing their processing.
   *
   * @param string $index_name
   *   Search API index name.
   * @param string $datasource_id
   *   Source of the data for the field. For example `entity:node` for the a
   *   node field.
   * @param \Drupal\field\Entity\FieldConfig $field_config
   *   Field config.
   * @param string $label
   *   Field label.
   */
  public function addFieldToIndex($index_name, $datasource_id, FieldConfig $field_config, $label) {
    $field_name = $field_config->getName();
    $field_type = $field_config->getType();

    // Skip unknown field types.
    $field_type_mapping = $this->allowedFieldTypes();
    if (!isset($field_type_mapping[$field_type]) || empty($field_type_mapping[$field_type])) {
      return;
    }

    // Load the search api index.
    $index = Index::load($index_name);
    if (empty($index)) {
      return;
    }

    // Skip existing fields.
    if ($index->getField($field_name)) {
      return;
    }

    // Add the base field.
    if ($field_type !== 'geofield') {
      $field = new Field($index, $field_name);
      $field->setType($field_type_mapping[$field_type]);
      $field->setPropertyPath($field_name);
      $field->setDatasourceId($datasource_id);
      $field->setLabel($label);
      $index->addField($field);
    }
    // For geofield fields we index the latlon property which is a string
    // with the latitude and longitude separated by a comma.
    // @todo review if that's the most appropriate wau to store a geofield.
    // @see https://www.drupal.org/project/search_api_location
    else {
      $field = new Field($index, $field_name . '_latlon_');
      $field->setType('string');
      $field->setPropertyPath($field_name . ':latlon');
      $field->setDatasourceId($datasource_id);
      $field->setLabel($label . ' (latlon)');
      $index->addField($field);
    }

    // Add extra fields.
    switch ($field_type) {
      // For entity reference fields, index the referenced entity label.
      case 'node_reference':
      case 'term_reference':
      case 'entity_reference':
      case 'entity_reference_uuid':
        // Index file data.
        if ($field_name === 'files') {
          $media_fields = [
            'name' => 'string',
          ];
          foreach ($media_fields as $extra_field_name => $extra_field_type) {
            $field = new Field($index, $field_name . '_media_' . $extra_field_name . '_');
            $field->setType($extra_field_type);
            $field->setPropertyPath($field_name . ':entity:' . $extra_field_name);
            $field->setDatasourceId($datasource_id);
            $field->setLabel($label . ' (media ' . $extra_field_name . ')');
            $index->addField($field);
          }
        }
        // Otherwise index the referenced entity label field.
        else {
          // Get the type of entity referenced by this field.
          $target_entity_type_id = $field_config
            ->getFieldStorageDefinition()
            ->getSetting('target_type');

          // Get the label field for the target entity type.
          $label_field = $this->entityTypeManager
            ->getStorage($target_entity_type_id)
            ->getEntityType()
            ->getKey('label');

          // Index the label field.
          $field = new Field($index, $field_name . '_label_');
          $field->setType('string');
          $field->setPropertyPath($field_name . ':entity:' . $label_field);
          $field->setDatasourceId($datasource_id);
          $field->setLabel($label . ' (' . $label_field . ')');
          $index->addField($field);
        }
        break;

      // For link fields, index the link title.
      case 'link':
        $field = new Field($index, $field_name . '_title_');
        $field->setType('string');
        $field->setPropertyPath($field_name . ':title');
        $field->setDatasourceId($datasource_id);
        $field->setLabel($label . ' (title)');
        $index->addField($field);
        break;

      // For daterange fields, index the end date.
      // @todo check the search api daterange type to see if it's
      // necessary to index the end date like that.
      case 'datarange':
        $field = new Field($index, $field_name . '_end_');
        $field->setType('date');
        $field->setPropertyPath($field_name . ':end_value');
        $field->setDatasourceId($datasource_id);
        $field->setLabel($label . ' (end)');
        $index->addField($field);
        break;

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
    $node_type->setThirdPartySetting('docstore', 'shared', $params['shared'] ?? TRUE);
    $node_type->setThirdPartySetting('docstore', 'private', !$node_type->getThirdPartySetting('docstore', 'shared'));

    // Can other providers add content.
    $node_type->setThirdPartySetting('docstore', 'content_allowed', $params['content_allowed'] ?? TRUE);

    // Can other providers add fields.
    $node_type->setThirdPartySetting('docstore', 'fields_allowed', $params['fields_allowed'] ?? TRUE);

    // Set base information.
    $node_type->setThirdPartySetting('docstore', 'provider_uuid', $this->provider->uuid());
    $node_type->setThirdPartySetting('docstore', 'author', $params['author']);
    $node_type->setThirdPartySetting('docstore', 'allow_duplicates', $params['allow_duplicates'] ?? TRUE);
    $node_type->setThirdPartySetting('docstore', 'endpoint', $params['endpoint']);

    // Track revisions by default.
    $node_type->setNewRevision($params['use_revisions'] ?? TRUE);

    $node_type->save();

    // Add files field.
    $this->createDocumentBaseFieldFiles($machine_name);

    // Add author.
    $this->createDocumentBaseFieldAuthor($machine_name);

    // Add private.
    $this->createDocumentBaseFieldPrivate($machine_name);

    // Create index.
    $config_path = drupal_get_path('module', 'docstore') . '/config/install/search_api.index.documents.yml';
    $data = Yaml::parseFile($config_path);

    $uuid_service = \Drupal::service('uuid');
    $data['uuid'] = $uuid_service->generate();
    $data['id'] = 'documents_' . $machine_name;
    $data['name'] = 'Index for ' . $machine_name;
    $data['datasource_settings']['entity:node']['bundles']['default'] = FALSE;
    $data['datasource_settings']['entity:node']['bundles']['selected'] = [$machine_name];
    \Drupal::configFactory()->getEditable('search_api.index.documents_' . $machine_name)->setData($data)->save(TRUE);

    // Update rendered item on search index.
    $index = Index::load('documents_' . $machine_name);
    if ($rendered_item_field = $index->getField('rendered_item')) {
      $rendered_item_config = $rendered_item_field->getConfiguration();
      if (!isset($rendered_item_config['view_mode']['entity:node'][$machine_name])) {
        $rendered_item_config['view_mode']['entity:node'][$machine_name] = 'search_index';
        $rendered_item_field->setConfiguration($rendered_item_config);
        $index->save();
      }
    }

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

    if ($node_type->getThirdPartySetting('docstore', 'provider_uuid') !== $this->provider->uuid()) {
      throw new \Exception('Only the owner can update');
    }

    // Update name/label.
    if (isset($params['label'])) {
      $node_type->set('name', $params['label']);
    }

    // Mark document type as shared.
    if (isset($params['shared'])) {
      $node_type->setThirdPartySetting('docstore', 'shared', $params['shared']);
      $node_type->setThirdPartySetting('docstore', 'private', !$node_type->getThirdPartySetting('docstore', 'shared'));
    }

    if (isset($params['private'])) {
      $node_type->setThirdPartySetting('docstore', 'shared', !$params['private']);
      $node_type->setThirdPartySetting('docstore', 'private', !$node_type->getThirdPartySetting('docstore', 'shared'));
    }

    // Can other providers add content.
    if (isset($params['content_allowed'])) {
      $node_type->setThirdPartySetting('docstore', 'content_allowed', $params['content_allowed']);
    }

    // Can other providers add fields.
    if (isset($params['fields_allowed'])) {
      $node_type->setThirdPartySetting('docstore', 'fields_allowed', $params['fields_allowed']);
    }

    if (isset($params['allow_duplicates'])) {
      $node_type->setThirdPartySetting('docstore', 'allow_duplicates', $params['allow_duplicates'] ?? TRUE);
    }

    // Track revisions.
    if (isset($params['use_revisions'])) {
      $node_type->setNewRevision($params['use_revisions']);
    }

    $node_type->save();

    return $node_type;
  }

  /**
   * Get document fields.
   */
  public function getDocumentFields() {
    $data = [];
    $map = $this->entityFieldManager->getFieldDefinitions('node', $this->nodeType);
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

    return $data;
  }

  /**
   * Get vocabulary fields.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   The vocabulary.
   */
  public function getVocabularyFields(Vocabulary $vocabulary) {
    $data = [];
    $map = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $vocabulary->id());
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
    if (!$node_type) {
      throw new \Exception('Document type does not exist');
    }

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
    // Create new machine name if needed.
    $field_name = $machine_name;
    if (empty($field_name)) {
      $field_name = $this->generateUniqueMachineName($label, 'node');
    }

    // Create storage if needed.
    $field_storage = FieldStorageConfig::loadByName('node', $field_name);

    if (empty($field_storage)) {
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

    if (empty($view_display)) {
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
      'settings' => [],
    ])->save();

    // Add to index.
    $this->addDocumentFieldToIndex($field_config, $label);

    return $field_name;
  }

  /**
   * Create reference document field.
   */
  protected function createDocumentReferenceField($author, $label, $machine_name, $type, $bundle, $multiple, $required, $private) {
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

    if (empty($field_storage)) {
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
    if (empty($view_display)) {
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
    $this->addDocumentFieldToIndex($field_config, $label);

    return $field_name;
  }

  /**
   * Get document field.
   */
  public function getDocumentField($field_name) {
    $field_config = FieldConfig::loadByName('node', $this->nodeType, $field_name);
    if (empty($field_config)) {
      throw new NotFoundHttpException('Field does not exist');
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
    if (empty($field_config)) {
      throw new NotFoundHttpException('Field does not exist');
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

    $field_config->save();

    return $field_name;
  }

  /**
   * Delete document field.
   *
   * Delete field on all content types.
   */
  public function deleteDocumentField($field_name) {
    $field_storage = FieldStorageConfig::loadByName('node', $field_name);
    if (empty($field_storage)) {
      throw new NotFoundHttpException('Field does not exist');
    }

    $field_storage->delete();

    return $field_name;
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
    $vocabulary->setThirdPartySetting('docstore', 'use_revisions', $params['use_revisions'] ?? TRUE);
    $vocabulary->save();

    // Add created field.
    $this->createVocabularyBaseFieldCreated($machine_name);

    // Add provider uuid.
    $this->createVocabularyBaseFieldProviderUuid($machine_name);

    // Add author HID.
    $this->createVocabularyBaseFieldHidId($machine_name);

    // Create index.
    $config_path = drupal_get_path('module', 'docstore') . '/config/install/search_api.index.terms.yml';
    $data = Yaml::parseFile($config_path);

    $uuid_service = \Drupal::service('uuid');
    $data['uuid'] = $uuid_service->generate();
    $data['id'] = 'terms_' . $machine_name;
    $data['name'] = 'Index for terms of ' . $machine_name;
    $data['datasource_settings']['entity:taxonomy_term']['bundles']['default'] = FALSE;
    $data['datasource_settings']['entity:taxonomy_term']['bundles']['selected'] = [$machine_name];
    \Drupal::configFactory()->getEditable('search_api.index.terms_' . $machine_name)->setData($data)->save(TRUE);

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
   *   Updated vocabulary.
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

    if (isset($params['shared'])) {
      $vocabulary->setThirdPartySetting('docstore', 'shared', $params['shared']);
      $vocabulary->setThirdPartySetting('docstore', 'private', !$vocabulary->getThirdPartySetting('docstore', 'shared'));
      unset($params['shared']);
    }

    if (isset($params['private'])) {
      $vocabulary->setThirdPartySetting('docstore', 'shared', !$params['private']);
      $vocabulary->setThirdPartySetting('docstore', 'private', !$vocabulary->getThirdPartySetting('docstore', 'shared'));
      unset($params['private']);
    }

    // Can other providers add content.
    if (isset($params['content_allowed'])) {
      $vocabulary->setThirdPartySetting('docstore', 'content_allowed', $params['content_allowed']);
      unset($params['content_allowed']);
    }

    // Can other providers add fields.
    if (isset($params['fields_allowed'])) {
      $vocabulary->setThirdPartySetting('docstore', 'fields_allowed', $params['fields_allowed']);
      unset($params['fields_allowed']);
    }

    // Revisions.
    if (isset($params['use_revisions'])) {
      $vocabulary->setThirdPartySetting('docstore', 'use_revisions', $params['use_revisions']);
      unset($params['use_revisions']);
    }

    // Check allow_duplicate changes.
    if (isset($params['allow_duplicates']) && $params['allow_duplicates'] === FALSE && $vocabulary->getThirdPartySetting('docstore', 'allow_duplicates') === TRUE) {
      // Check all existing terms.
      // @todo we only need to check if there is at least one duplicate.
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
        // @todo review the type of exception and make the message more explicit
        // to indicate that it's not possible to update the `allow_duplicates`
        // setting.
        throw new \Exception('Vocabulary contains duplicate terms');
      }

      $vocabulary->setThirdPartySetting('docstore', 'allow_duplicates', $params['allow_duplicates'] ?? TRUE);
      unset($params['allow_duplicates']);
    }

    // Update all fields specified in params.
    $updated_fields = [];
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
        throw new \Exception(strtr('Field @name does not exist', ['@name' => $name]));
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
   *
   * @return \Drupal\taxonomy\Entity\Vocabulary
   *   Delete vocabulary.
   */
  public function deleteVocabulary(Vocabulary $vocabulary) {
    // Provider can only update own vocabulary.
    if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $this->provider->uuid()) {
      throw new \Exception('Vocabulary is not owned by you');
    }

    // Delete the index.
    $index = Index::load('terms_' . $vocabulary->id());
    if ($index) {
      $index->delete();
    }

    $vocabulary->delete();

    return $vocabulary;
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
    if (empty($field_config)) {
      throw new NotFoundHttpException('Field does not exist');
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
    if (empty($field_config)) {
      throw new NotFoundHttpException('Field does not exist');
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

    $field_config->save();

    return $field_name;
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
    if (empty($field_config)) {
      throw new NotFoundHttpException('Field does not exist');
    }

    $field_config->delete();

    return $field_name;
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
    if (empty($field_storage)) {
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

    // Add to index.
    $this->addTermFieldToIndex($field_config, $label);

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
    if (empty($field_storage)) {
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

    // Add to index.
    $this->addTermFieldToIndex($field_config, $label);

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
    if (empty($field_storage)) {
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

    $this->addTermFieldToIndex($field_config, $label);
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
    if (empty($field_storage)) {
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

    $this->addTermFieldToIndex($field_config, $label);
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
    if (empty($field_storage)) {
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

    $this->addTermFieldToIndex($field_config, $label);
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
    if (empty($field_storage)) {
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

    $this->addDocumentFieldToIndex($field_config, $label);
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
    if (empty($field_storage)) {
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

    $this->addDocumentFieldToIndex($field_config, $label);
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
    if (empty($field_storage)) {
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

    $this->addDocumentFieldToIndex($field_config, $label);
  }

  /**
   * Get document facets.
   */
  public function getDocumentFacets() {
    return $this->getIndexFacets('documents_' . $this->nodeType);
  }

  /**
   * Set document facets.
   */
  public function setDocumentFacets($facets) {
    return $this->setIndexFacets('documents_' . $this->nodeType, $facets);
  }

  /**
   * Get vocabulary facets.
   */
  public function getVocabularyFacets($type) {
    return $this->getIndexFacets('terms_' . $type);
  }

  /**
   * Set vocabulary facets.
   */
  public function setVocabularyFacets($type, $facets) {
    return $this->setIndexFacets('terms_' . $type, $facets);
  }

  /**
   * Get facets of an index.
   */
  public function getIndexFacets($index_name) {
    $index = Index::load($index_name);
    if (empty($index)) {
      return;
    }

    return $index->getThirdPartySetting('docstore', 'facets', []);
  }

  /**
   * Set facets for an index.
   */
  public function setIndexFacets($index_name, $facets) {
    $index = Index::load($index_name);
    if (empty($index)) {
      return;
    }

    $index->setThirdPartySetting('docstore', 'facets', $facets);
    $index->save();

    return $facets;
  }

  /**
   * Get the list of protected fields that cannot be created.
   *
   * @param string $entity_type_id
   *   Entity type (ex: taxonomy_term, node).
   *
   * @return array
   *   List of fields with the field name as keys.
   *
   * @todo use that function when creating fields to force the generation
   * of a machine_name.
   * @todo add another function to check validity of field and prohibit field
   * names starting with a `_`.
   */
  public function getProtectedFields($entity_type_id) {
    static $cache;

    // Store in a static cache as this can be called many times.
    if (isset($cache[$entity_type_id])) {
      return $cache[$entity_type_id];
    }

    $protected_fields = [
      // Response often include a message so we mark it as protected to prohibit
      // the creation of custom fields named `message`.
      'message' => TRUE,
      // New revision is a flag to instruct the docstore to create a revision.
      'new_revision' => TRUE,
      // Normalized field name for the revision id field.
      'revision_id' => TRUE,
      // Normalized field name for the revision default field.
      'draft' => TRUE,
      // For entity without a provider_uuid base field (ex: nodes).
      'provider_uuid' => TRUE,
      // Special properties.
      'files' => TRUE,
      'metadata' => TRUE,
      'private' => TRUE,
      // Search api fields.
      // @todo there are possibly more fields and it would be better to
      // investigate how to get the full list.
      'boost_document' => TRUE,
      'rendered_item' => TRUE,
      'search_api_datasource' => TRUE,
      'search_api_id' => TRUE,
      'search_api_language' => TRUE,
      'search_api_relevance' => TRUE,
    ];

    /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->getEntityType();

    // Add the entity keys and their normalized $field name.
    foreach ($entity_type->getKeys() as $key => $field) {
      $protected_fields[$key] = TRUE;
      $protected_fields[$field] = TRUE;
    }
    // Normalized label for the revision field.
    $protected_fields['revision_id'] = TRUE;

    // Add the revision keys and their normalized $field name.
    foreach ($entity_type->getRevisionMetadataKeys() as $key => $field) {
      $protected_fields[$key] = TRUE;
      $protected_fields[$field] = TRUE;
    }

    // Get the field definitions that include base and custom fields.
    $base_field_definitions = $this->entityFieldManager
      ->getBaseFieldDefinitions($entity_type_id);
    foreach ($base_field_definitions as $field => $definition) {
      $protected_fields[$field] = TRUE;
    }

    // Additional fields. That corresponds to the transformations performed
    // when "massaging" the resource data before responding.
    // @see \Drupal\docstore\ResourceTrait::massageResourceDataForEntityType()
    switch ($entity_type_id) {
      case 'taxonomy_term':
        $protected_fields += [
          'vocabulary' => TRUE,
          'vocabulary_uuid' => TRUE,
        ];
        break;

      case 'node':
        $protected_fields += [
          'type' => TRUE,
          'type_uuid' => TRUE,
        ];
        break;
    }

    $cache[$entity_type_id] = $protected_fields;
    return $protected_fields;
  }

  /**
   * Get status of a document type.
   */
  public function getDocumentStatus() {
    $result = [];

    // Load the search api index.
    $index = Index::load('documents_' . $this->nodeType);
    if (empty($index)) {
      return;
    }

    $result = [
      'total' => $index->getTrackerInstance()->getTotalItemsCount(),
      'remaining' => $index->getTrackerInstance()->getRemainingItemsCount(),
      'indexed' => $index->getTrackerInstance()->getIndexedItemsCount(),
    ];

    $indexed_fields = $this->getIndexedDocumentFields();
    $document_fields = $this->getDocumentFields();
    $diff = array_diff_key($document_fields, $indexed_fields);
    if (isset($diff['revision_timestamp'])) {
      unset($diff['revision_timestamp']);
    }
    if (isset($diff['revision_uid'])) {
      unset($diff['revision_uid']);
    }
    if (isset($diff['revision_log'])) {
      unset($diff['revision_log']);
    }
    if (isset($diff['status'])) {
      unset($diff['status']);
    }
    if (isset($diff['uid'])) {
      unset($diff['uid']);
    }
    if (isset($diff['promote'])) {
      unset($diff['promote']);
    }
    if (isset($diff['sticky'])) {
      unset($diff['sticky']);
    }
    if (isset($diff['default_langcode'])) {
      unset($diff['default_langcode']);
    }
    if (isset($diff['revision_default'])) {
      unset($diff['revision_default']);
    }
    if (isset($diff['revision_translation_affected'])) {
      unset($diff['revision_translation_affected']);
    }

    $result['missing_fields'] = array_keys($diff);
    $result['document_fields'] = array_keys($document_fields);
    $result['indexed_fields'] = array_keys($indexed_fields);

    return $result;
  }

  /**
   * Get status of a vocabulary.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   The vocabulary.
   */
  public function getVocabularyStatus(Vocabulary $vocabulary) {
    $result = [];

    // Load the search api index.
    $index = Index::load('terms_' . $vocabulary->id());
    if (empty($index)) {
      return;
    }

    $result = [
      'total' => $index->getTrackerInstance()->getTotalItemsCount(),
      'remaining' => $index->getTrackerInstance()->getRemainingItemsCount(),
      'indexed' => $index->getTrackerInstance()->getIndexedItemsCount(),
    ];

    $indexed_fields = $this->getIndexedTermFields($vocabulary->id());
    $vocabulary_fields = $this->getVocabularyFields($vocabulary);
    $diff = array_diff_key($vocabulary_fields, $indexed_fields);
    if (isset($diff['revision_timestamp'])) {
      unset($diff['revision_timestamp']);
    }
    if (isset($diff['revision_uid'])) {
      unset($diff['revision_uid']);
    }
    if (isset($diff['revision_created'])) {
      unset($diff['revision_created']);
    }
    if (isset($diff['revision_user'])) {
      unset($diff['revision_user']);
    }
    if (isset($diff['revision_log_message'])) {
      unset($diff['revision_log_message']);
    }
    if (isset($diff['revision_log'])) {
      unset($diff['revision_log']);
    }
    if (isset($diff['status'])) {
      unset($diff['status']);
    }
    if (isset($diff['uid'])) {
      unset($diff['uid']);
    }
    if (isset($diff['promote'])) {
      unset($diff['promote']);
    }
    if (isset($diff['sticky'])) {
      unset($diff['sticky']);
    }
    if (isset($diff['default_langcode'])) {
      unset($diff['default_langcode']);
    }
    if (isset($diff['revision_default'])) {
      unset($diff['revision_default']);
    }
    if (isset($diff['revision_translation_affected'])) {
      unset($diff['revision_translation_affected']);
    }
    if (isset($diff['weight'])) {
      unset($diff['weight']);
    }
    if (isset($diff['parent'])) {
      unset($diff['parent']);
    }
    if (isset($diff['geolocation'])) {
      unset($diff['geolocation']);
    }

    $result['missing_fields'] = array_keys($diff);
    $result['vocabulary_fields'] = array_keys($vocabulary_fields);
    $result['indexed_fields'] = array_keys($indexed_fields);

    return $result;
  }

}
