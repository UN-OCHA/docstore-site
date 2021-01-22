<?php

namespace Drupal\docstore\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\State;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ManageFields;
use Drupal\docstore\MetadataTrait;
use Drupal\entity_usage\EntityUsage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for vocabulary endpoints.
 */
class VocabularyController extends ControllerBase {

  use MetadataTrait;
  use ProviderTrait;

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
    $cache_tags['#cache'] = [
      'tags' => $cache_tags,
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Create vocabulary.
   */
  public function createVocabulary(Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Get provider.
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

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Get vocabulary.
   */
  public function getVocabulary($id) {
    $vocabulary = $this->loadVocabulary($id);

    $data = [
      'uuid' => $vocabulary->uuid(),
      'machine_name' => $vocabulary->id(),
      'label' => $vocabulary->label(),
      'description' => $vocabulary->getDescription(),
    ];

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $vocabulary->getCacheTags() + ['vocabularies'],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Update vocabulary.
   */
  public function updateVocabulary($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($id);

    // Get provider.
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

    $response = new JsonResponse($data);

    return $response;
  }

  /**
   * Delete vocabulary.
   */
  public function deleteVocabulary($id, Request $request) {
    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($id);

    // Check if vocabulary is in use.
    if ($this->entityInUse($vocabulary)) {
      throw new BadRequestHttpException('Vocabulary is in use and can not be deleted');
    }

    // Get provider.
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

    $response = new JsonResponse($data);
    return $response;
  }

  /**
   * Get vocabulary terms.
   */
  public function getVocabularyTerms($id, Request $request) {
    $vocabulary = $this->loadVocabulary($id);

    $offset = $request->get('offset') ?? 0;
    $limit = $request->get('limit') ?? 100;
    $data = $this->loadTerms([$vocabulary->id()], NULL, $offset, $limit);

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => array_merge(['terms'], $vocabulary->getCacheTags()),
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags)->addCacheContexts([
      'user',
      'url.query_args:filter',
      'url.query_args:sort',
      'url.query_args:page',
    ]));

    return $response;
  }

  /**
   * Get vocabulary fields.
   */
  public function getVocabularyFields($id) {
    $vocabulary = $this->loadVocabulary($id);

    $data = [];
    $map = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $vocabulary->id());
    foreach ($map as $field_name => $field_info) {
      $data[$field_name] = $field_info->getType();
    }

    $response = new JsonResponse($data);
    return $response;
  }

  /**
   * Get vocabulary field.
   */
  public function getVocabularyField($id, $field_id) {
    $vocabulary = $this->loadVocabulary($id);

    // Get provider.
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
    $cache_tags['#cache'] = [
      'tags' => [
        'vocabulary_fields',
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags));

    return $response;
  }

  /**
   * Create vocabulary fields.
   */
  public function createVocabularyField($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load provider.
    $provider = $this->requireProvider();

    // Id is either UUID or machine name.
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

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Update vocabulary fields.
   */
  public function updateVocabularyField($id, $field_id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load provider.
    $provider = $this->requireProvider();

    // Id is either UUID or machine name.
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

    $response = new JsonResponse($data);
    $response->setStatusCode(200);

    return $response;
  }

  /**
   * Delete vocabulary fields.
   */
  public function deleteVocabularyField($id, $field_id, Request $request) {
    // Load provider.
    $provider = $this->requireProvider();

    // Id is either UUID or machine name.
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

    $response = new JsonResponse($data);
    $response->setStatusCode(200);

    return $response;
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
   * Create term on vocabulary.
   */
  public function createTermOnVocabulary($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    $vocabulary = $this->loadVocabulary($id);

    // Get provider.
    $provider = $this->requireProvider();

    // Check if provider can create terms.
    if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $provider->uuid()) {
      if (!$vocabulary->getThirdPartySetting('docstore', 'content_allowed', FALSE)) {
        throw new \Exception(strtr('You are not allowed to create new terms in @vocabulary', ['@vocabulary' => $vocabulary->label()]));
      }
    }

    $params['vocabulary'] = $vocabulary->uuid();
    $data = $this->createTermFromUserParameters($params);

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Create term on vocabulary.
   */
  public function createTermOnVocabularyInBulk($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    $vocabulary = $this->loadVocabulary($id);

    // Get provider.
    $provider = $this->requireProvider();

    // Check if provider can create terms.
    if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $provider->uuid()) {
      if (!$vocabulary->getThirdPartySetting('docstore', 'content_allowed', FALSE)) {
        throw new \Exception(strtr('You are not allowed to create new terms in @vocabulary', ['@vocabulary' => $vocabulary->label()]));
      }
    }

    if (empty($params['terms'])) {
      throw new BadRequestHttpException('terms is required');
    }

    $data = [];

    foreach ($params['terms'] as $term) {
      // Add common fields.
      $term['author'] = $params['author'];
      $term['vocabulary'] = $vocabulary->uuid();

      // Create term.
      $data[] = $this->createTermFromUserParameters($term);
    }

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Create term.
   */
  public function createTerm($id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    $vocabulary = $this->loadVocabulary($id);

    $params['vocabulary'] = $vocabulary->uuid();
    $data = $this->createTermFromUserParameters($params);

    $response = new JsonResponse($data);
    $response->setStatusCode(201);

    return $response;
  }

  /**
   * Create term.
   */
  protected function createTermFromUserParameters($params) {
    // Get provider.
    $provider = $this->requireProvider();

    // Check required fields.
    if (empty($params['label'])) {
      throw new BadRequestHttpException('Label is required');
    }

    if (empty($params['vocabulary'])) {
      throw new BadRequestHttpException('Vocabulary is required');
    }

    if (empty($params['author'])) {
      throw new BadRequestHttpException('Author is required');
    }

    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($params['vocabulary']);

    if ($vocabulary->getThirdPartySetting('docstore', 'allow_duplicates') === FALSE) {
      // Check for duplicate labels.
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'name' => $params['label'],
        'vid' => $vocabulary->id(),
      ]);
      if ($terms) {
        throw new BadRequestHttpException('Term with same label already exists');
      }
    }

    try {
      $term = $this->createTermFromParameters($params, $vocabulary, $provider);
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException($e->getMessage(), $e, 400);
    }

    $data = [
      'message' => 'Term created',
      'uuid' => $term->uuid(),
    ];

    return $data;
  }

  /**
   * Get term.
   */
  public function getTerm($id, $term_id) {
    $vocabulary = $this->loadVocabulary($id);

    $params['vocabulary'] = $vocabulary->uuid();

    // Load term.
    $term = $this->loadTerm($term_id);
    $terms = $this->loadTerms([$id], $term->id(), 0, 1);
    $data = reset($terms);

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => [
        'terms',
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags)->addCacheContexts([
      'user',
      'url.query_args:filter',
      'url.query_args:sort',
      'url.query_args:page',
    ]));

    return $response;
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

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $term->getCacheTags(),
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags)->addCacheContexts([
      'user',
      'url.query_args:filter',
      'url.query_args:sort',
      'url.query_args:page',
    ]));

    return $response;
  }

  /**
   * Get term revision.
   */
  public function getTermRevision($id, $term_id, $revision_id, Request $request) {
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\term\Entity\Term $term */
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadRevision($revision_id);
    if ($term->uuid() !== $term_id) {
      throw new BadRequestHttpException('Revision not found');
    }

    if ($term->getVocabularyId() !== $vocabulary->id()) {
      throw new BadRequestHttpException('Revision not found');
    }

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

    // Add cache tags.
    $cache_tags['#cache'] = [
      'tags' => $term->getCacheTags(),
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($cache_tags)->addCacheContexts([
      'user',
    ]));

    return $response;
  }

  /**
   * Update term.
   */
  public function updateTerm($id, $term_id, Request $request) {
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

    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    // Load term.
    $term = $this->loadTerm($term_id);

    $vocabulary = $this->loadVocabulary($id);
    if ($term->getVocabularyId() !== $vocabulary->id()) {
      throw new BadRequestHttpException('Revision not found');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only update own terms.
    if ($term->provider_uuid->entity->uuid() !== $provider->uuid()) {
      throw new BadRequestHttpException('Term is not owned by you');
    }

    // Check required fields.
    if ($request->getMethod() === 'PUT') {
      if (empty($params['label'])) {
        throw new BadRequestHttpException('Label is required');
      }
    }

    // Label is actually name.
    if (isset($params['label'])) {
      $params['name'] = $params['label'];
      unset($params['label']);

      if ($vocabulary->getThirdPartySetting('docstore', 'allow_duplicates') === FALSE) {
        // Check for duplicate labels.
        $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
          'name' => $params['name'],
          'vid' => $vocabulary->id(),
        ]);

        if ($term->label() === $params['name'] && count($terms) > 1) {
          throw new BadRequestHttpException('Term with same label already exists');
        }

        if ($term->label() !== $params['name'] && count($terms) >= 1) {
          throw new BadRequestHttpException('Term with same label already exists');
        }
      }
    }

    $updated_fields = [];

    // Update all fields specified in metadata.
    if (isset($params['metadata'])) {
      $metadata = $params['metadata'];
      if (!is_array($metadata) || $this->arrayIsAssociative($metadata)) {
        throw new BadRequestHttpException('Metadata has to be an array');
      }

      foreach ($metadata as $metaitem) {
        foreach ($metaitem as $name => $values) {
          // Make sure protected fields aren't set.
          if (isset($protected_fields[$name])) {
            throw new BadRequestHttpException(strtr('Field @name cannot be changed', ['@name' => $name]));
          }

          if ($term->hasField($name)) {
            $term->set($name, $values);
            $updated_fields[] = $name;
          }
          else {
            throw new BadRequestHttpException(strtr('Field @name does not exists', ['@name' => $name]));
          }
        }
      }
      unset($params['metadata']);
    }

    // Update all fields specified in params.
    foreach ($params as $name => $values) {
      // Ignore revision fields.
      if ($name === 'new_revision' || $name === 'revision_log' || $name === 'draft') {
        continue;
      }

      // Make sure protected fields aren't set.
      if (isset($protected_fields[$name])) {
        throw new BadRequestHttpException(strtr('Field @name cannot be changed', ['@name' => $name]));
      }

      if ($term->hasField($name)) {
        $term->set($name, $values);
        $updated_fields[] = $name;
      }
      else {
        throw new BadRequestHttpException(strtr('Field @name does not exists', ['@name' => $name]));
      }
    }

    // Remove all fields not part of params.
    if ($request->getMethod() === 'PUT') {
      $term_fields = $term->getFields(FALSE);
      foreach ($term_fields as $term_field) {
        // Skip name field.
        if ($term_field->getName() === 'name') {
          continue;
        }

        if (in_array($term_field->getName(), $updated_fields)) {
          continue;
        }

        if (in_array($term_field->getName(), $protected_fields)) {
          continue;
        }

        if (!$term_field->isEmpty()) {
          $term->set($term_field->getName(), NULL);
        }
      }
    }

    // Check if we need to create a new revision.
    $term->setRevisionCreationTime(time());

    if ($vocabulary->getThirdPartySetting('docstore', 'use_revisions', TRUE) || $params['new_revision'] ?? FALSE) {
      $term->revision_log = 'Updated';
      if (isset($params['revision_log'])) {
        $term->revision_log = $params['revision_log'];
        unset($params['revision_log']);
      }
      $term->setNewRevision();
      $term->setRevisionUserId($provider->id());

      // Save new revision as draft?
      $term->isDefaultRevision(TRUE);
      if ($params['draft'] ?? FALSE) {
        $term->isDefaultRevision(FALSE);
      }
    }

    $term->save();

    $data = [
      'message' => 'Term updated',
      'uuid' => $term->uuid(),
    ];

    $response = new JsonResponse($data);
    return $response;
  }

  /**
   * Publish term revision.
   */
  public function publishTermRevision($id, $term_id, $revision_id, Request $request) {
    // Parse JSON.
    $params = json_decode($request->getContent(), TRUE);
    if (empty($params) || !is_array($params)) {
      throw new BadRequestHttpException('You have to pass a JSON object');
    }

    /** @var \Drupal\term\Entity\Term $term */
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadRevision($revision_id);
    if ($term->uuid() !== $term_id) {
      throw new BadRequestHttpException('Revision not found');
    }

    $vocabulary = $this->loadVocabulary($id);
    if ($term->getVocabularyId() !== $vocabulary->id()) {
      throw new BadRequestHttpException('Revision not found');
    }

    // Get provider.
    $provider = $this->requireProvider();

    // Provider can only update own terms.
    if ($term->provider_uuid->entity->uuid() !== $provider->uuid()) {
      throw new BadRequestHttpException('Term is not owned by you');
    }

    if (!$term->isDefaultRevision()) {
      $term->setRevisionCreationTime(time());
      $term->revision_log = 'Updated';
      if (isset($params['revision_log'])) {
        $term->revision_log = $params['revision_log'];
      }
      $term->setNewRevision();
      $term->setRevisionUserId($provider->id());

      $term->isDefaultRevision(TRUE);
      $term->save();
    }

    $data = [
      'message' => 'Term updated',
      'uuid' => $term->uuid(),
    ];

    $response = new JsonResponse($data);
    return $response;
  }

  /**
   * Delete term.
   */
  public function deleteTerm($id, $term_id, Request $request) {
    // Load term.
    $term = $this->loadTerm($term_id);

    // Get provider.
    $provider = $this->requireProvider();

    $vocabulary = $this->loadVocabulary($id);
    if ($term->getVocabularyId() !== $vocabulary->id()) {
      throw new BadRequestHttpException('Wrong vocabulary');
    }

    // Provider can only update own terms.
    if ($term->provider_uuid->entity->uuid() !== $provider->uuid()) {
      throw new BadRequestHttpException('Term is not owned by you');
    }

    // Check if vocabulary is accessible.
    if (!$this->providerCanUseVocabulary($term->getVocabularyId(), $provider)) {
      throw new BadRequestHttpException('The vocabulary is private');
    }

    // Check if term is in use.
    if ($this->entityInUse($term)) {
      throw new BadRequestHttpException('Term is in use and can not be deleted');
    }

    $data = [
      'message' => 'Term deleted',
      'uuid' => $term->uuid(),
    ];

    $term->delete();

    $response = new JsonResponse($data);

    return $response;
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
  protected function loadVocabulary($id) {
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
  protected function loadVocabularies() {
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
  protected function loadTerm($id) {
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

    /** @var \Drupal\Core\Entity\Term $term */
    foreach ($terms as $term) {
      // Make sure user has access to the vocabulary.
      if (!$this->providerCanUseVocabulary($term->getVocabularyId(), $provider)) {
        continue;
      }

      $vocabulary = $this->loadVocabulary($term->getVocabularyId());
      $row = [
        'uuid' => $term->uuid(),
        'label' => $term->label(),
        'machine_name' => $term->id(),
        'vocabulary_name' => $term->getVocabularyId(),
        'vocabulary_uuid' => $vocabulary->uuid(),
        'changed' => date(DATE_ATOM, $term->getChangedTime()),
      ];

      // Add all fields.
      $term_fields = $term->getFields();
      foreach ($term_fields as $term_field) {
        if (in_array($term_field->getName(), $hide_fields)) {
          continue;
        }

        // Make sure user has access to the field.
        if (!$this->providerCanUseField($term_field->getName(), $term->getVocabularyId(), 'taxonomy_term', $provider)) {
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
   * Check if in entity is in use.
   *
   * \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if entity is used somewhere.
   */
  protected function entityInUse($entity) {
    return !empty($this->entityUsage->listSources($entity));
  }

  /**
   * Check if an array is associative.
   */
  protected function arrayIsAssociative(array $array) {
    return count(array_filter(array_keys($array), 'is_string')) > 0;
  }

  /**
   * Get list of accessible vocabularies.
   */
  protected function getAccessibleVocabularies($provider) {
    static $cache = [];

    if (isset($cache[$provider->id])) {
      return $cache[$provider->id];
    }

    $cache[$provider->id] = [];

    $vocabularies = $this->loadVocabularies();
    foreach ($vocabularies as $vocabulary) {
      if (!$provider->isAnonymous() && $vocabulary->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
        $cache[$provider->id][] = $vocabulary->id();
      }

      if ($vocabulary->getThirdPartySetting('docstore', 'shared')) {
        $cache[$provider->id][] = $vocabulary->id();
      }
    }

    return $cache[$provider->id];
  }

  /**
   * Check if provider can use vocabulary.
   */
  protected function providerCanUseVocabulary($vocabulary_id, $provider) {
    static $cache = [];

    if (isset($cache[$vocabulary_id][$provider->id])) {
      return $cache[$vocabulary_id][$provider->id];
    }

    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($vocabulary_id);

    // Owner has access.
    if (!$provider->isAnonymous() && $vocabulary->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      $cache[$vocabulary_id][$provider->id] = TRUE;
      return $cache[$vocabulary_id][$provider->id];
    }

    if ($vocabulary->getThirdPartySetting('docstore', 'shared')) {
      $cache[$vocabulary_id][$provider->id] = TRUE;
      return $cache[$vocabulary_id][$provider->id];
    }

    $cache[$vocabulary_id][$provider->id] = FALSE;
    return $cache[$vocabulary_id][$provider->id];
  }

}
