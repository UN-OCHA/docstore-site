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
use Drupal\Core\State\State;
use Drupal\docstore\MetadataTrait;
use Drupal\docstore\ProviderTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\docstore\RevisionableResourceTrait;
use Drupal\docstore\SearchableResourceTrait;
use Drupal\entity_usage\EntityUsage;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Controller for term endpoints.
 */
class TermController extends ControllerBase {

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
   * Get terms.
   *
   * @param string $id
   *   Vocabulary id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the list of terms.
   */
  public function getTerms($id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Get the terms.
    return $this->searchResources($request, 'taxonomy_term', $vocabulary);
  }

  /**
   * Get term.
   *
   * @param string $id
   *   Vocabulary id or uuid.
   * @param string $term_id
   *   Term id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the term's data.
   */
  public function getTerm($id, $term_id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Get the term's data.
    return $this->searchResources($request, 'taxonomy_term', $vocabulary, $term_id);
  }

  /**
   * Get term revisions.
   *
   * @param string $id
   *   Vocabulary id or uuid.
   * @param string $term_id
   *   Term id or uuid.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the term's data including the revision list.
   */
  public function getTermRevisions($id, $term_id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Get the term's data including the revision list.
    return $this->searchResources($request, 'taxonomy_term', $vocabulary, $term_id, TRUE);
  }

  /**
   * Get term revision.
   *
   * @param string $id
   *   Vocabulary id or uuid.
   * @param string $term_id
   *   Term id or uuid.
   * @param string $revision_id
   *   Revision ID or "last".
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Docstore API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the term revision's data.
   */
  public function getTermRevision($id, $term_id, $revision_id, Request $request) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    // Get the provider.
    $provider = $this->getProvider();

    // Load the revision.
    $revision = $this->loadResourceEntityRevision($term_id, $revision_id, 'taxonomy_term', $vocabulary, $provider);

    // Prepare the response data.
    $data = $this->prepareEntityResourceDataForResponse($revision, $provider);

    // Add cache contexts and tags.
    $cache = $this->createResponseCache()
      ->addCacheTags(['terms'])
      ->addCacheableDependency($revision)
      ->addCacheableDependency($vocabulary);

    return $this->createCacheableJsonResponse($cache, $data, 200);
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

    /** @var \Drupal\user\UserInterface $provider */
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
   *
   * @param string $id
   *   Vocabulary uuid or machine_name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function createTerm($id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Create the term.
    $data = $this->createTermFromParameters($vocabulary, $params, $provider, TRUE);

    // Notifiy webhooks.
    docstore_notify_webhooks('term:' . $vocabulary->id() . ':create', $data['uuid']);
    docstore_notify_webhooks('term:create', [
      'uuid' => $data['uuid'],
      'vocabulary' => $vocabulary->id(),
    ]);

    return $this->createJsonResponse($data, 201);
  }

  /**
   * Create term from a set of parameters.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param array $params
   *   Parameters to create a term from.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $full_output
   *   Whether to return the full term's data or only the uuid.
   *
   * @return array
   *   Associative array with the term uuid and a "Term created" message.
   */
  public function createTermFromParameters(Vocabulary $vocabulary, array $params, UserInterface $provider, $full_output = FALSE) {
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
      // @todo What about the "changed" date? Check how it's set when creating
      // the term because it seems to be earlier than the `created` date.
      'created' => time(),
      'provider_uuid' => [
        'target_uuid' => $provider->uuid(),
      ],
      'parent' => [],
      'description' => $params['description'] ?? '',
    ];

    // Add support for hierarchical vocabularies.
    if (isset($params['parent'])) {
      if (is_array($params['parent'])) {
        // Allow lookup by label.
        $target_entity = $this->findTargetByProperty($params['parent']);
        if (!empty($target_entity)) {
          $item['parent'] = [
            'target_id' => $target_entity->id(),
          ];
        }
      }
      else {
        if (Uuid::isValid($params['parent'])) {
          $parent = $this->loadTerm($params['parent']);
          $item['parent'] = [
            'target_id' => $parent->id(),
          ];
        }
        else {
          // Assume it's a regular id.
          $parent = $this->loadTerm($params['parent']);
          $item['parent'] = [
            'target_id' => $parent->id(),
          ];
        }
      }
    }

    // Add all other fields.
    $item = array_merge($item, $this->buildItemDataFromParams($params, 'taxonomy_term', $vocabulary->id(), $provider, $params['author']));

    // Create term.
    $term = Term::create($item);

    // Create a new revision if necessary.
    $this->createEntityRevisionFromParameters($term, $params, $provider);

    // Validate and save the term.
    $this->validateAndSaveEntity($term);

    // Invalidate cache.
    Cache::invalidateTags(['terms']);

    return [
      'message' => 'Term created',
    ] + $this->prepareEntityResourceDataForResponse($term, $provider, $full_output);
  }

  /**
   * Update term.
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
  public function updateTerm($id, $term_id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Pass the term id to load the term.
    $params['id'] = $term_id;

    // Update the term.
    $data = $this->updateTermFromParameters($vocabulary, $params, $provider, $request->getMethod() === 'PUT');

    // Notifiy webhooks.
    docstore_notify_webhooks('term:' . $vocabulary->id() . ':update', $data['uuid']);
    docstore_notify_webhooks('term:update', [
      'uuid' => $data['uuid'],
      'vocabulary' => $vocabulary->id(),
    ]);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Update a term from provided parameters.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param array $params
   *   Parameters to update the term with.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   * @param bool $full_update
   *   Perform a full update or a partial one.
   *
   * @return array
   *   Associative array with the term uuid and a "Term updated" message.
   */
  public function updateTermFromParameters(Vocabulary $vocabulary, array $params, UserInterface $provider, $full_update = TRUE) {
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
    $updated_fields = $this->updateEntityFieldsFromParameters($term, $params, $provider);

    // Empty all the fields that were not updated.
    if ($full_update) {
      $this->resetEntityFields($term, $provider, $updated_fields);
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
   * Publish term revision.
   *
   * @param string $id
   *   Vocabulary uuid or machine_name.
   * @param string $term_id
   *   Term uuid or machine_name.
   * @param string $revision_id
   *   Revision id.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   API request.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   JSON response with the update message and term uuid.
   *
   * @todo provide a way to unpublish a revision.
   */
  public function publishTermRevision($id, $term_id, $revision_id, Request $request) {
    // Parse JSON.
    $params = $this->getRequestContent($request);

    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->loadVocabulary($id);

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Load the revision.
    $revision = $this->loadResourceEntityRevision($term_id, $revision_id, 'taxonomy_term', $vocabulary, $provider);

    // A term's revision can only be updated by its owner.
    $this->providerIsOwner($revision, $provider, 'provider_uuid');

    // Publish the revision.
    $this->publishEntityRevisionFromParameters($revision, $params, $provider);

    $data = [
      'message' => 'Term revision published',
      'uuid' => $revision->uuid(),
    ];

    // Add cache tags.
    $cache = $this->createResponseCache()->addCacheableDependency($revision);

    // We cache the response because once published there is no way to unpublish
    // it so no need to try again.
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

    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->requireProvider();

    // Add the term id to the parameters to load the term.
    $params['id'] = $term_id;

    // Delete the term.
    $data = $this->deleteTermFromParameters($vocabulary, $params, $provider);

    // Notifiy webhooks.
    docstore_notify_webhooks('term:' . $vocabulary->id() . ':delete', $data['uuid']);
    docstore_notify_webhooks('term:delete', [
      'uuid' => $data['uuid'],
      'vocabulary' => $vocabulary->id(),
    ]);

    return $this->createJsonResponse($data, 200);
  }

  /**
   * Delete a term from provided parameters.
   *
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param array $params
   *   Parameters to delete the term with.
   * @param \Drupal\user\UserInterface $provider
   *   Provider.
   *
   * @return array
   *   Associative array with the term uuid and a "Term deleted" message.
   */
  public function deleteTermFromParameters(Vocabulary $vocabulary, array $params, UserInterface $provider) {
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
    $this->getAccessibleResourceTypes('taxonomy_vocabulary', $provider, $vocabulary->id());

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
    if (!empty($query->count()->execute())) {
      throw new BadRequestHttpException('Term with same label already exists');
    }
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
   * Load a term.
   *
   * @param string $id
   *   The term uuid or entity_id.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   Term.
   */
  public function loadTerm($id) {
    /** @var \Drupal\taxonomy\Entity\Term */
    return $this->loadResourceEntity('taxonomy_term', $id);
  }

}
