<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\State;
use Drupal\docstore\ManageFields;
use Drupal\docstore\MetadataTrait;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\entity_usage\EntityUsage;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for vocabulary endpoints.
 */
class VocabularyController extends ControllerBase {

  use MetadataTrait;
  use ProviderTrait;
  use ResourceTrait;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The state store.
   *
   * @var \Drupal\entity_usage\EntityUsage
   */
  protected $entityUsage;

  /**
   * Protected term fields.
   *
   * @var array
   */
  protected static $protectedTermFields = [
    'author' => TRUE,
    'provider_uuid' => TRUE,
    'changed' => TRUE,
    'created' => TRUE,
    'default_langcode' => TRUE,
    'langcode' => TRUE,
    'parent' => TRUE,
    'revision_created' => TRUE,
    'revision_id' => TRUE,
    'revision_log_message' => TRUE,
    'revision_user' => TRUE,
    'status' => TRUE,
    'tid' => TRUE,
    'uuid' => TRUE,
    'vid' => TRUE,
    'vocabulary' => TRUE,
    'weight' => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config,
      Connection $database,
      EntityFieldManagerInterface $entityFieldManager,
      EntityRepositoryInterface $entityRepository,
      EntityTypeManagerInterface $entityTypeManager,
      LoggerChannelFactoryInterface $logger_factory,
      State $state,
      EntityUsage $entityUsage
    ) {
    $this->config = $config;
    $this->database = $database;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityRepository = $entityRepository;
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $logger_factory;
    $this->state = $state;
    $this->entityUsage = $entityUsage;
  }

  /**
   * Get vocabularies.
   */
  public function getVocabularies() {
    $data = [];
    $cache_tags = [];

    $vocabularies = $this->loadVocabularies();
    foreach ($vocabularies as $vocabulary) {
      $data[] = [
        'uuid' => $vocabulary->uuid(),
        'machine_name' => $vocabulary->id(),
        'label' => $vocabulary->label(),
        'description' => $vocabulary->getDescription(),
      ];

      $cache_tags = array_merge($cache_tags, $vocabulary->getCacheTags());
    }

    // Add cache tags.
    $cache = [
      'tags' => $cache_tags,
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create vocabulary.
   */
  public function createVocabulary(Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $vocabulary = $manager->createVocabulary($params);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    $data = [
      'message' => 'Vocabulary created',
      'machine_name' => $vocabulary->id(),
      'uuid' => $vocabulary->uuid(),
    ];

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Get vocabulary.
   */
  public function getVocabulary($id) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    $data = [
      'uuid' => $vocabulary->uuid(),
      'machine_name' => $vocabulary->id(),
      'label' => $vocabulary->label(),
      'description' => $vocabulary->getDescription(),
    ];

    // Add cache tags.
    $cache = [
      'tags' => array_merge($vocabulary->getCacheTags(), ['vocabularies']),
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Update vocabulary.
   */
  public function updateVocabulary($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Update vocabulary.
    try {
      $vocabulary = $manager->updateVocabulary($vocabulary, $params, $request->getMethod());
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Vocabulary updated',
      'uuid' => $vocabulary->uuid(),
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete vocabulary.
   */
  public function deleteVocabulary($id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Check if vocabulary is in use.
    if ($this->entityInUse($vocabulary)) {
      throw new BadRequestHttpException('Vocabulary is in use and can not be deleted');
    }

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Delete.
    try {
      $vocabulary = $manager->deleteVocabulary($vocabulary);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Vocabulary deleted',
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get vocabulary terms.
   */
  public function getVocabularyTerms($id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // @todo add some validation of the parameters.
    $offset = $request->get('offset') ?? 0;
    $limit = $request->get('limit') ?? 100;
    $data = $this->loadTerms([$vocabulary->id()], NULL, $offset, $limit);

    // Add cache tags and contexts.
    $cache = [
      'contexts' => [
        'user',
        'url.query_args:filter',
        'url.query_args:sort',
        'url.query_args:page',
      ],
      'tags' => array_merge(['terms'], $vocabulary->getCacheTags()),
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get vocabulary fields.
   */
  public function getVocabularyFields($id) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    $data = [];
    $map = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $vocabulary->id());
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

    // Add cache tags.
    $cache = [
      'tags' => [
        'vocabulary_fields',
      ],
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get vocabulary field.
   */
  public function getVocabularyField($id, $field_id) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    // Get field config.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    try {
      $data = $manager->getVocabularyField($vocabulary, $field_id);
    }
    catch (\Exception $exception) {
      throw new NotFoundHttpException($exception->getMessage());
    }

    // Add cache tags.
    $cache = [
      'tags' => [
        'vocabulary_fields',
      ],
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create vocabulary fields.
   */
  public function createVocabularyField($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $field_name = $manager->addVocabularyField($vocabulary, $params);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Field created',
      'field_name' => $field_name,
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Update vocabulary fields.
   */
  public function updateVocabularyField($id, $field_id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $manager->updateVocabularyField($vocabulary, $field_id, $params);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Field updated',
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete vocabulary fields.
   */
  public function deleteVocabularyField($id, $field_id, Request $request) {
    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->database);

    // Create field.
    try {
      $manager->deleteVocabularyField($vocabulary, $field_id);
    }
    catch (\Exception $exception) {
      throw new BadRequestHttpException($exception->getMessage());
    }

    $data = [
      'message' => 'Field deleted',
    ];

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get vocabulary machine name.
   */
  protected function getVocabularyMachineName($id) {
    if (Uuid::isValid($id)) {
      $vocabulary = $this->entityRepository->loadEntityByUuid('taxonomy_vocabulary', $id);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($id);
      if (!$vocabulary) {
        throw new BadRequestHttpException('Invalid vocabulary');
      }
    }

    return $vocabulary;
  }

  /**
   * Process terms (create, update, delete) in bulk.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   API response.
   */
  public function processTermsInBulk($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    // Check if the provider can create/update/delete terms.
    $this->providerCanCreateUpdateDelete($vocabulary, $provider);

    // @todo move all those checks in a separate class to validate request
    // content.
    //
    // Check that the author property is set.
    if (empty($params['author']) || !is_string($params['author'])) {
      throw new BadRequestHttpException('The "author" property is required and must be a string');
    }
    $author = $params['author'];

    // Check that the list of terms is present.
    if (empty($params['terms']) || !is_array($params['terms'])) {
      throw new BadRequestHttpException('The "terms" property is required and must be an array.');
    }

    $data = [];
    $method = $request->getMethod();
    foreach ($params['terms'] as $term) {
      try {
        switch ($method) {
          case 'POST':
            // We only add the author when creating terms as it cannot be
            // changed afterwards.
            $term['author'] = $author;
            $data[] = $this->createTermFromParameters($vocabulary, $term, $provider);
            break;

          case 'PUT':
            $data[] = $this->updateTermFromParameters($vocabulary, $term, $provider, TRUE);
            break;

          case 'PATCH':
            $data[] = $this->updateTermFromParameters($vocabulary, $term, $provider, FALSE);
            break;

          case 'DELETE':
            $data[] = $this->deleteTermFromParameters($vocabulary, $term, $provider);
            break;

          default:
            throw new BadRequestHttpException('Unrecognized bulk operation');
        }
      }
      catch (\Exception $exception) {
        $code = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
        $data[] = [
          'error' => [
            'status' => $code,
            'message' => $exception->getMessage(),
          ],
        ];
      }

    }

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Create term.
   */
  public function createTerm($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    // Create the term.
    $data = $this->createTermFromParameters($vocabulary, $params, $provider);

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Create term from a set of parameters.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param array $params
   *   Parameters to create a term from.
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   *
   * @return array
   *   Associative array with the term uuid and a "Term created" message.
   */
  public function createTermFromParameters(Vocabulary $vocabulary, array $params, AccountInterface $provider) {
    // Check if provider can create terms.
    $this->providerCanCreateUpdateDelete($vocabulary, $provider);

    // Check required fields.
    if (empty($params['label'])) {
      throw new BadRequestHttpException('Label is required');
    }
    if (empty($params['author'])) {
      throw new BadRequestHttpException('Author is required');
    }

    // Check if a term with the same label already exists.
    if ($vocabulary->getThirdPartySetting('docstore', 'allow_duplicates') === FALSE) {
      $this->checkForDuplicates($vocabulary->id(), $params['label']);
    }

    // Term.
    $item = [
      'name' => $params['label'],
      'vid' => $vocabulary->id(),
      'author' => $params['author'],
      'created' => [],
      'provider_uuid' => [],
      'parent' => [],
      'description' => $params['description'] ?? '',
    ];

    // Set creation time.
    $item['created'][] = [
      'value' => time(),
    ];

    // Set owner.
    // @todo check that as it seems to be different from other resources
    // where only provider is allowed.
    $item['provider_uuid'][] = [
      'target_uuid' => $provider->uuid(),
    ];

    // Check for meta tags.
    if (isset($params['metadata']) && $params['metadata']) {
      $metadata = $params['metadata'];
      $item = array_merge($item, $this->buildItemDataFromMetaData($metadata, $vocabulary->id(), $provider, $params['author'], 'term'));
    }

    // Create term.
    $term = Term::create($item);

    // Check for invalid fields.
    foreach ($item as $key => $data) {
      if (!$term->hasField($key)) {
        throw new BadRequestHttpException(strtr('Unknown field @field', [
          '@field' => $key,
        ]));
      }
    }

    // Validate and save the term.
    $this->validateAndSaveEntity($term);

    // Invalidate cache.
    Cache::invalidateTags(['terms']);

    // @todo check if we want to return the entire term's data.
    return [
      'message' => 'Term created',
      'uuid' => $term->uuid(),
    ];
  }

  /**
   * Get term.
   */
  public function getTerm($id, $term_id) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Load term.
    $term = $this->loadTerm($term_id);
    $terms = $this->loadTerms([$vocabulary->id()], $term->id(), 0, 1);
    $data = reset($terms);

    // Add cache tags and contexts.
    $cache = [
      'contexts' => [
        'user',
        'url.query_args:filter',
        'url.query_args:sort',
        'url.query_args:page',
      ],
      'tags' => [
        'terms',
      ],
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get term revisions.
   */
  public function getTermRevisions($id, $term_id, Request $request) {
    // Load term.
    $term = $this->loadTerm($term_id);
    $terms = $this->loadTerms([$id], $term->id(), 0, 1);
    $data = reset($terms);

    $revisions = $this->database->select('taxonomy_term_revision', 'tr')
      ->fields('tr', [
        'revision_id',
        'revision_created',
        'revision_default',
        'revision_user',
        'revision_log_message',
      ])
      ->condition('tr.tid', $term->id())
      ->orderBy('revision_id', 'DESC')
      ->execute()
      ->fetchAll();

    $data['revisions'] = $revisions;

    // Add cache tags and contexts.
    $cache = [
      'contexts' => [
        'user',
        'url.query_args:filter',
        'url.query_args:sort',
        'url.query_args:page',
      ],
      'tags' => $term->getCacheTags(),
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get term revision.
   */
  public function getTermRevision($id, $term_id, $revision_id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\term\Entity\Term $term */
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadRevision($revision_id);
    if ($term->uuid() !== $term_id) {
      throw new NotFoundHttpException('Revision not found');
    }
    // Make sure the term belongs to the vocabulary.
    $this->validateEntityBundle($term, $vocabulary);

    $data = [];
    $term_fields = $term->getFields(TRUE);
    foreach ($term_fields as $term_field) {
      $field_item_list = isset($term->{$term_field->getName()}) ? $term->{$term_field->getName()} : NULL;
      $field_item_definition = $field_item_list->getFieldDefinition();

      $values = [];
      foreach ($field_item_list as $field_item) {
        if ($main_property_name = $field_item->mainPropertyName()) {
          $values[] = $field_item->getValue()[$main_property_name];
        }
        else {
          $values[] = $field_item->getValue();
        }
      }

      $field_name = $term_field->getName();
      if ($field_name === 'name') {
        $field_name = 'label';
      }

      if ($field_item_definition->getFieldStorageDefinition()->getCardinality() == 1) {
        $data[$field_name] = reset($values);
      }
      else {
        $data[$field_name] = $values;
      }
    }

    // Add cache tags and contexts.
    $cache = [
      'contexts' => [
        'user',
      ],
      'tags' => $term->getCacheTags(),
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Update term.
   */
  public function updateTerm($id, $term_id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    // Pass the term id to load the term.
    $params['id'] = $term_id;

    // Update the term.
    $data = $this->updateTermFromParameters($vocabulary, $params, $provider, $request->getMethod() === 'PUT');

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Update a term from provided parameters.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param array $params
   *   Parameters to update the term with.
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   * @param bool $full_update
   *   Perform a full update or a partial one.
   *
   * @return array
   *   Associative array with the term uuid and a "Term updated" message.
   */
  public function updateTermFromParameters(Vocabulary $vocabulary, array $params, AccountInterface $provider, $full_update = TRUE) {
    // Check if provider can update terms.
    $this->providerCanCreateUpdateDelete($vocabulary, $provider);

    // Load term.
    $term_id = $params['uuid'] ?? $params['id'] ?? '';
    if (empty($term_id)) {
      throw new BadRequestHttpException('Term id is required');
    }
    $term = $this->loadTerm($term_id);
    unset($params['uuid']);
    unset($params['id']);

    // Make sure the term belongs to the vocabulary.
    $this->validateEntityBundle($term, $vocabulary);

    // A term can only be updated by its owner.
    $this->providerIsOwner($term, $provider, 'provider_uuid');

    // Check required fields.
    if ($full_update) {
      if (empty($params['label'])) {
        throw new BadRequestHttpException('Label is required');
      }
    }

    // Set the name parameter and check if a duplicate exists.
    if (isset($params['label'])) {
      // Label is actually name.
      $params['name'] = $params['label'];
      unset($params['label']);

      if ($vocabulary->getThirdPartySetting('docstore', 'allow_duplicates') === FALSE) {
        $this->checkForDuplicates($vocabulary->id(), $params['name'], $term->id());
      }
    }

    // The vocabulary cannot be changed.
    // No need to throw an error because we already checked earlier that the
    // term belongs to the given vocabulary.
    unset($params['vocabulary']);

    // Remove the author as it cannot be changed.
    unset($params['author']);

    // Update the term fields from the given parameters.
    $updated_fields = $this->updateEntityFieldsFromParameters($term, $params, static::$protectedTermFields);

    // Remove all fields not part of params.
    if ($full_update) {
      $this->emptyEntityFields($term, ['name' => TRUE] + $updated_fields + static::$protectedTermFields);
    }

    // Create a new revision if necessary.
    $this->createEntityRevisionFromParameters($term, $params, $provider);

    // Validate and save the term.
    $this->validateAndSaveEntity($term);

    // Invalidate cache.
    Cache::invalidateTags(['terms']);

    return [
      'message' => 'Term updated',
      'uuid' => $term->uuid(),
    ];
  }

  /**
   * Check if a term with the same name already exists.
   *
   * @param string $vocabulary_id
   *   Vocabulary id.
   * @param string $label
   *   Term label.
   * @param string $term_id
   *   Term ID to exclude from the check.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   400 Bad Request if there are other terms with the same name.
   */
  public function checkForDuplicates($vocabulary_id, $label, string $term_id = '') {
    // Check if there are other terms with the same label for the gien
    // vocabulary.
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();
    $query->condition('name', $label, '=');
    $query->condition('vid', $vocabulary_id, '=');

    // If an existing term is passed, exclude it from the results.
    if (!empty($term_id)) {
      $query->condition('tid', $term_id, '<>');
    }

    // If there are other terms with the same label, throw an error.
    if ($query->count()->execute() > 0) {
      throw new BadRequestHttpException('Term with same label already exists');
    }
  }

  /**
   * Publish term revision.
   */
  public function publishTermRevision($id, $term_id, $revision_id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    // Check for last.
    if ($revision_id === 'last') {
      // Get last revisions.
      $query = $this->database->select('taxonomy_term_revision', 'ttr')
        ->fields('ttr', ['revision_id']);
      $query->innerJoin('taxonomy_term_data', 'ttd', 'ttr.tid = ttd.tid');
      $revision_id = $query->condition('ttd.uuid', $term_id)
        ->orderBy('revision_id', 'DESC')
        ->execute()
        ->fetchCol(0);

      $revision_id = reset($revision_id);
    }

    /** @var \Drupal\term\Entity\Term $term */
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadRevision($revision_id);
    if (!$term || $term->uuid() !== $term_id) {
      throw new BadRequestHttpException('Revision not found');
    }

    // Make sure the term belongs to the vocabulary.
    $this->validateEntityBundle($term, $vocabulary);

    // A term can only be updated by its owner.
    $this->providerIsOwner($term, $provider, 'provider_uuid');

    // Publish the revision.
    $this->publishEntityRevisionFromParameters($term, $params, $provider);

    $data = [
      // @todo change message.
      'message' => 'Term updated',
      'uuid' => $term->uuid(),
    ];

    // Add cache tags.
    $cache = [
      'tags' => $term->getCacheTags(),
    ];

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Delete term.
   *
   * @param string $id
   *   Vocabulary uuid or machine_name.
   * @param string $term_id
   *   Term uuid or machine_name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function deleteTerm($id, $term_id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->requireProvider();

    // Add the term id to the parameters to load the term.
    $params['id'] = $term_id;

    // Delete the term.
    $data = $this->deleteTermFromParameters($vocabulary, $params, $provider);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete a term from provided parameters.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param array $params
   *   Parameters to delete the term with.
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   *
   * @return array
   *   Associative array with the term uuid and a "Term deleted" message.
   */
  public function deleteTermFromParameters(Vocabulary $vocabulary, array $params, AccountInterface $provider) {
    // Check if provider can delete terms.
    $this->providerCanCreateUpdateDelete($vocabulary, $provider);

    // Load term.
    $term_id = $params['uuid'] ?? $params['id'] ?? '';
    if (empty($term_id)) {
      throw new BadRequestHttpException('Term id is required');
    }
    $term = $this->loadTerm($term_id);

    // Make sure the term belongs to the vocabulary.
    $this->validateEntityBundle($term, $vocabulary);

    // A term can only be delete by its owner.
    $this->providerIsOwner($term, $provider, 'provider_uuid');

    // Check if vocabulary is accessible.
    if (!$this->providerCanUseVocabulary($term->bundle(), $provider)) {
      throw new BadRequestHttpException('The vocabulary is private');
    }

    // Check if term is in use.
    if ($this->entityInUse($term)) {
      throw new BadRequestHttpException('Term is in use and can not be deleted');
    }

    // Delete the term.
    $term->delete();

    // Invalidate cache.
    Cache::invalidateTags(['terms']);

    return [
      'message' => 'Term deleted',
      'uuid' => $term->uuid(),
    ];
  }

  /**
   * Load a vocabulary.
   *
   * @param string $id
   *   The vocabulary uuid or entity_id.
   *
   * @return \Drupal\taxonomy\Entity\Vocabulary
   *   Vocabulary.
   */
  public function loadVocabulary($id) {
    if (Uuid::isValid($id)) {
      $vocabulary = $this->entityRepository->loadEntityByUuid('taxonomy_vocabulary', $id);
    }
    else {
      // Assume it's the machine name.
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($id);
    }

    if (!$vocabulary) {
      throw new NotFoundHttpException('Vocabulary does not exist');
    }

    return $vocabulary;
  }

  /**
   * Load all vocabularies.
   *
   * @return \Drupal\taxonomy\Entity\Vocabulary[]
   *   Vocabularies.
   */
  public function loadVocabularies() {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();

    // @todo check access.
    return $vocabularies;
  }

  /**
   * Load a term.
   *
   * @param string $id
   *   The term uuid or entity_id.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   Term.
   */
  public function loadTerm($id) {
    if (Uuid::isValid($id)) {
      $term = $this->entityRepository->loadEntityByUuid('taxonomy_term', $id);
    }
    else {
      // Assume it's the machine name.
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($id);
    }

    if (!$term) {
      throw new NotFoundHttpException('Term does not exist');
    }

    return $term;
  }

  /**
   * Fetch terms.
   */
  public function loadTerms($vids = [], $tid = NULL, $offset = 0, $limit = 100) {
    /** @var \Drupal\Core\Session\AccountInterface $provider */
    $provider = $this->getProvider();
    $data = [];

    // Fields to hide.
    $hide_fields = [
      'tid',
      'revision_id',
      'vid',
      'revision_created',
      'revision_user',
      'revision_log_message',
      'weight',
      'changed',
      'parent',
      'changed',
    ];

    if ($tid) {
      $tids[] = $tid;
    }
    else {
      // Build query.
      $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();

      // @todo add paging.
      $query->range($offset, $limit);

      // Filter by vocabularies.
      $accessible_vocabularies = $this->getAccessibleVocabularies($provider);
      if (empty($accessible_vocabularies)) {
        throw new BadRequestHttpException('You do not have access to any vocabulary');
      }

      if (!empty($vids)) {
        $vids = array_intersect($vids, $accessible_vocabularies);
        if (empty($vids)) {
          throw new BadRequestHttpException('You do not have access to this vocabulary');
        }
        $query->condition('vid', $vids, 'IN');
      }
      else {
        $query->condition('vid', $accessible_vocabularies, 'IN');
      }

      $tids = $query->execute();
    }

    // Load terms.
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);

    /** @var \Drupal\taxonomy\Entity\Term $term */
    foreach ($terms as $term) {
      // Make sure user has access to the vocabulary.
      if (!$this->providerCanUseVocabulary($term->bundle(), $provider)) {
        continue;
      }

      /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
      $vocabulary = $this->loadVocabulary($term->bundle());
      $row = [
        'uuid' => $term->uuid(),
        'label' => $term->label(),
        'machine_name' => $term->id(),
        'vocabulary_name' => $term->bundle(),
        'vocabulary_uuid' => $vocabulary->uuid(),
        'changed' => date(DATE_ATOM, $term->getChangedTime()),
      ];

      if ($term->parent && count($term->parent->referencedEntities())) {
        $parents = $term->parent->referencedEntities();
        $row['parent'] = [
          'uuid' => $parents[0]->uuid(),
          'label' => $parents[0]->label(),
        ];
      }

      // Add all fields.
      $term_fields = $term->getFields();
      foreach ($term_fields as $term_field) {
        if (in_array($term_field->getName(), $hide_fields)) {
          continue;
        }

        // Make sure user has access to the field.
        if (!$this->providerCanUseField($term_field->getName(), $term->bundle(), 'taxonomy_term', $provider)) {
          continue;
        }

        $field_item_list = isset($term->{$term_field->getName()}) ? $term->{$term_field->getName()} : NULL;
        $field_item_definition = $field_item_list->getFieldDefinition();
        $values = [];

        foreach ($field_item_list as $field_item) {
          if ($main_property_name = $field_item->mainPropertyName()) {
            $values[] = $field_item->getValue()[$main_property_name];
          }
          else {
            $values[] = $field_item->getValue();
          }
        }

        if ($field_item_definition->getFieldStorageDefinition()->getCardinality() == 1) {
          $row[$term_field->getName()] = reset($values);
        }
        else {
          $row[$term_field->getName()] = $values;
        }
      }

      // Format timestamp.
      $row['created'] = date(DATE_ATOM, $row['created']);

      $data[] = $row;
    }

    return $data;
  }

  /**
   * Get list of accessible vocabularies.
   */
  protected function getAccessibleVocabularies($provider) {
    static $cache = [];

    if (isset($cache[$provider->id()])) {
      return $cache[$provider->id()];
    }

    $cache[$provider->id()] = [];

    $vocabularies = $this->loadVocabularies();
    foreach ($vocabularies as $vocabulary) {
      if (!$provider->isAnonymous() && $vocabulary->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
        $cache[$provider->id()][] = $vocabulary->id();
      }

      if ($vocabulary->getThirdPartySetting('docstore', 'shared')) {
        $cache[$provider->id()][] = $vocabulary->id();
      }
    }

    return $cache[$provider->id()];
  }

  /**
   * Check if provider can use vocabulary.
   *
   * @todo review moving that to the provider trait.
   */
  protected function providerCanUseVocabulary($vocabulary_id, $provider) {
    static $cache = [];

    if (isset($cache[$vocabulary_id][$provider->id()])) {
      return $cache[$vocabulary_id][$provider->id()];
    }

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($vocabulary_id);

    // Owner has access.
    if (!$provider->isAnonymous() && $vocabulary->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      $cache[$vocabulary_id][$provider->id()] = TRUE;
      return $cache[$vocabulary_id][$provider->id()];
    }

    if ($vocabulary->getThirdPartySetting('docstore', 'shared')) {
      $cache[$vocabulary_id][$provider->id()] = TRUE;
      return $cache[$vocabulary_id][$provider->id()];
    }

    $cache[$vocabulary_id][$provider->id()] = FALSE;
    return $cache[$vocabulary_id][$provider->id()];
  }

}
