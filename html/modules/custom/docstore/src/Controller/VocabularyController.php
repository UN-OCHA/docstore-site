<?php

namespace Drupal\docstore\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Drupal\docstore\MetadataTrait;
use Drupal\docstore\ManageFields;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\docstore\RevisionableResourceTrait;
use Drupal\docstore\SearchableResourceTrait;
use Drupal\entity_usage\EntityUsage;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller for vocabulary endpoints.
 */
class VocabularyController extends ControllerBase {

  use MetadataTrait;
  use ProviderTrait;
  use ResourceTrait;
  use RevisionableResourceTrait;
  use SearchableResourceTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
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
   * Search API index.
   *
   * @var string
   */
  protected $searchApiIndex = 'terms';

  /**
   * Search API full text search fields.
   *
   * @var array
   */
  protected $searchApiFullTextSearchFields = [
    'name',
  ];

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
  public function __construct(ConfigFactoryInterface $configFactory,
      Connection $database,
      EntityFieldManagerInterface $entityFieldManager,
      EntityRepositoryInterface $entityRepository,
      EntityTypeManagerInterface $entityTypeManager,
      LoggerChannelFactoryInterface $logger_factory,
      State $state,
      EntityUsage $entityUsage
    ) {
    $this->configFactory = $configFactory;
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
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of vocabularies.
   */
  public function getVocabularies(Request $request) {
    $data = [];
    $entity_type_id = 'taxonomy_vocabulary';

    // Set the response cache.
    $cache = $this->createResponseCache()->addCacheTags(['vocabularies']);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // Get the list of vocabularies accessible to the provider.
    $vocabulary_ids = $this->getAccessibleResourceTypes($entity_type_id, $provider);

    // Get the vocabulary storage and the id field.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $id_key = $storage->getEntityType()->getKey('id');

    /** @var \Drupal\taxonomy\Entity\Vocabulary[] $vocabularies */
    $vocabularies = $storage->loadByProperties([$id_key => $vocabulary_ids]);

    // Prepare the vocabularies data.
    foreach ($vocabularies as $vocabulary) {
      $data[] = $this->buildVocabularyJsonOutput($vocabulary);
      $cache->addCacheableDependency($vocabulary);
    }

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get vocabulary.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the vocabulary's data.
   */
  public function getVocabulary($id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->getProvider();

    // Set the response cache.
    $cache = $this->createResponseCache()
      ->addCacheTags(['vocabularies'])
      ->addCacheableDependency($vocabulary);

    // Check if the vocabulary is accessible to the provider.
    try {
      $this->getAccessibleResourceTypes('taxonomy_vocabulary', $provider, $vocabulary->id());
    }
    catch (AccessDeniedHttpException $exception) {
      throw new CacheableAccessDeniedHttpException($cache, 'You are not allowed to access the vocabulary');
    }

    $data = $this->buildVocabularyJsonOutput($vocabulary);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create vocabulary.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function createVocabulary(Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Delete field storage and config.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Create vocabulary.
    $vocabulary = $manager->createVocabulary($params);

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    // Reset the list of accessible vocabularies.
    $this->rebuildAccessibleResourceTypes('taxonomy_vocabulary');

    $data = [
      'message' => 'Vocabulary created',
    ] + $this->buildVocabularyJsonOutput($vocabulary);

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Update vocabulary.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   *
   * @todo consolidate logic with DocumentTypeController::updateDocumentType().
   */
  public function updateVocabulary($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Update vocabulary.
    $vocabulary = $manager->updateVocabulary($vocabulary, $params, $request->getMethod());

    // Reset the list of accessible vocabularies.
    $this->rebuildAccessibleResourceTypes('taxonomy_vocabulary');

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    $data = [
      'message' => 'Vocabulary updated',
      'uuid' => $vocabulary->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete vocabulary.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function deleteVocabulary($id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Check if vocabulary is in use.
    if ($this->entityInUse($vocabulary)) {
      throw new BadRequestHttpException('Vocabulary is in use and can not be deleted');
    }

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Delete vocabulary.
    $vocabulary = $manager->deleteVocabulary($vocabulary);

    // Reset the list of accessible vocabularies.
    $this->rebuildAccessibleResourceTypes('taxonomy_vocabulary');

    // Invalidate cache.
    Cache::invalidateTags(['vocabularies']);

    $data = [
      'message' => 'Vocabulary deleted',
      'uuid' => $vocabulary->uuid(),
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Get vocabulary fields.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of fields.
   *
   * @todo consolidate this and getVocabularyField().
   */
  public function getVocabularyFields($id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    $data = [];
    $map = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $vocabulary->id());
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

    // Add cache tags.
    $cache = $this->createResponseCache()->addCacheTags(['vocabulary_fields']);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Get vocabulary field.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param string $field_id
   *   Field id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the field information.
   *
   * @todo consolidate this and getVocabularyFields().
   */
  public function getVocabularyField($id, $field_id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\docstore\ManageFields $manager */
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Get the vocabulary field.
    $data = $manager->getVocabularyField($vocabulary, $field_id);

    // Add cache tags.
    $cache = $this->createResponseCache()->addCacheTags(['vocabulary_fields']);

    return $this->createCacheableJsonResponse($cache, $data, 200);
  }

  /**
   * Create vocabulary fields.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function createVocabularyField($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Create field.
    $field_name = $manager->addVocabularyField($vocabulary, $params);

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    $data = [
      'message' => 'Field created',
      'field_name' => $field_name,
    ];

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Update vocabulary fields.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param string $field_id
   *   Field id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function updateVocabularyField($id, $field_id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Create field.
    $field_name = $manager->updateVocabularyField($vocabulary, $field_id, $params);

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    $data = [
      'message' => 'Field updated',
      'field_name' => $field_name,
    ];

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete vocabulary fields.
   *
   * @param string $id
   *   Vocabulary ID.
   * @param string $field_id
   *   Field id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function deleteVocabularyField($id, $field_id, Request $request) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Create vocabulary field.
    $manager = new ManageFields($provider, '', $this->entityFieldManager, $this->entityTypeManager, $this->database);

    // Create field.
    $field_name = $manager->deleteVocabularyField($vocabulary, $field_id);

    // Invalidate cache.
    Cache::invalidateTags(['vocabulary_fields']);

    $data = [
      'message' => 'Field deleted',
      'field_name' => $field_name,
    ];

    return $this->createJsonResponse($data, 200);
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
    /** @var \Drupal\taxonomy\Entity\Vocabulary */
    return $this->loadResourceEntity('taxonomy_vocabulary', $id);
  }

  /**
   * Build the vocabulary data for the response.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   *
   * @return array
   *   Dara for the JSON response.
   */
  public function buildVocabularyJsonOutput(Vocabulary $vocabulary) {
    $data = [
      'uuid' => $vocabulary->uuid(),
      'machine_name' => $vocabulary->id(),
      'label' => $vocabulary->label(),
      'description' => $vocabulary->getDescription(),
    ];

    return $data;
  }

}
