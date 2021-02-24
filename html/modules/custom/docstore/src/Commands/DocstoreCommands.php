<?php

namespace Drupal\docstore\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\State;
use Drupal\docstore\ManageFields;
use Drupal\docstore\FileTrait;
use Drupal\docstore\ResourceTrait;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Yaml\Yaml;

/**
 * Docstore Drush commandfile.
 *
 * @property \Consolidation\Log\Logger $logger
 */
class DocstoreCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  // Drush traits.
  use ProcessManagerAwareTrait;
  use SiteAliasManagerAwareTrait;

  // Docstore traits.
  use FileTrait;
  use ResourceTrait;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

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
   * The mime type guesser service.
   *
   * @var \Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser
   */
  protected $mimeTypeGuesser;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The file usage.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(
      AccountInterface $current_user,
      ConfigFactoryInterface $config_factory,
      Connection $database,
      EntityFieldManagerInterface $entity_field_manager,
      EntityRepositoryInterface $entity_repository,
      EntityTypeManagerInterface $entity_type_manager,
      MimeTypeGuesser $mimeTypeGuesser,
      FileSystem $file_system,
      FileUsageInterface $file_usage,
      State $state
    ) {
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->fileSystem = $file_system;
    $this->fileUsage = $file_usage;
    $this->state = $state;
  }

  /**
   * Reset the docstore for testing.
   *
   * @command docstore:test-reset
   * @usage docstore:test-reset
   *   Reset the docstore for testing.
   * @validate-module-enabled docstore
   */
  public function resetTesting() {
    // Reload the configuration of the solr indices.
    // @todo do we really need to do that? This looks like the site's config
    // may become out of sync if those files are not properly maintained.
    $config_path = drupal_get_path('module', 'docstore') . '/config/install';
    $config_files = $this->fileSystem->scanDirectory($config_path, '/^search_api\.index\..*\.yml$/');
    foreach ($config_files as $file) {
      $settings = Yaml::parseFile($file->uri);
      $this->configFactory->getEditable($file->name)->setData($settings)->save(TRUE);
      $this->logger->info(strtr('Loaded @index_id index configuration', [
        '@index_id' => ltrim(strrchr($file->name, '.'), '.'),
      ]));
    }

    // Clear the solr Indices.
    //
    // We do that first to avoid unnecessary requests when deleting
    // the entities.
    $server_storage = $this->entityTypeManager->getStorage('search_api_server');
    /** @var \Drupal\search_api\ServerInterface $server */
    foreach ($server_storage->loadMultiple() as $server) {
      if ($server->hasValidBackend() && $server->getBackend() instanceof SolrBackendInterface) {
        // Clear the indices on the server.
        foreach ($server->getIndexes() as $index) {
          $index->clear();

          $this->logger->info(strtr('Cleared @index_id index on server @server_id', [
            '@index_id' => $index->id(),
            '@server_id' => $server->id(),
          ]));
        }

        // Make sure there is no leftover in solr.
        /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
        $backend = $server->getBackend();
        $connector = $backend->getSolrConnector();
        $update_query = $connector->getUpdateQuery()->addDeleteQuery('*:*');
        $connector->update($update_query);

        $this->logger->info(strtr('Delete all items on server @server_id', [
          '@server_id' => $server->id(),
        ]));
      }
    }
    $this->logger->info('Solr cleared');

    // Delete all the entities.
    // @todo check if this actually deletes files on disk.
    $entity_type_ids = [
      'node',
      'node_type',
      'taxonomy_term',
      'taxonomy_vocabulary',
      'media',
      'file',
      'webhook_config',
    ];
    foreach ($entity_type_ids as $entity_type_id) {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $resource_type = $this->getResourceType($entity_type_id);

      // Delete the entities 100 at a time.
      $count = 0;
      while (TRUE) {
        $ids = $storage->getQuery()->range(0, 100)->execute();
        if (empty($ids)) {
          break;
        }
        $storage->delete($storage->loadMultiple($ids));
        $count += count($ids);
      }

      // Invalidate the caches.
      Cache::invalidateTags([$resource_type]);

      $this->logger->info(strtr('@count @label removed.', [
        '@count' => $count,
        '@label' => $this->getResourceTypeLabel($entity_type_id, $count !== 1, TRUE),
      ]));
    }
    $this->logger->info('Entities cleared');

    // Reset the drupal states for the resource types and endpoints.
    $bundle_type_ids = [
      'node_type',
      'taxonomy_vocabulary',
      'media_type',
    ];
    foreach ($bundle_type_ids as $bundle_type_id) {
      $resource_type = $this->getResourceType($bundle_type_id);
      // Reset the accessible resource types and endpoints.
      $this->state->delete('docstore.resource_types.' . $resource_type);
      $this->state->delete('docstore.endpoints.' . $resource_type);
    }

    // Create the test users.
    $this->createTestUsers();

    // Rebuild cache.
    /** @var \Drush\SiteAlias\ProcessManager $process_manager */
    $process_manager = $this->processManager();
    $process = $process_manager->drush($this->siteAliasManager()->getSelf(), 'cache-rebuild');
    $process->mustrun();
    $this->logger->info('Cache cleared.');

    $this->logger->success('Reset complete.');
    return TRUE;
  }

  /**
   * Reset the docstore for testing.
   *
   * @param string $id
   *   Node type id.
   * @param string $endpoint
   *   Endpoint for the node type.
   *
   * @command docstore:test-create-node-type
   * @usage docstore:test-create-node-type id endpoint
   *   Create a node type with the id and endpoint.
   * @validate-module-enabled docstore
   */
  public function createTestNodeType($id, $endpoint) {
    // Nothing to do if the node type already exists.
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($id);
    if (!empty($node_type)) {
      $this->logger->info(strtr('Node type @id already exists.', [
        '@id' => $id,
      ]));
      return TRUE;
    }

    // Ensure the tests users are created.
    $this->createTestUsers();

    // Load the base provider.
    $provider = $this->entityTypeManager->getStorage('user')->load(2);

    // Create the document type.
    $manager = new ManageFields($provider, $id, $this->entityFieldManager, $this->entityTypeManager, $this->database);
    $manager->createDocumentType([
      'label' => ucfirst($id),
      'machine_name' => $id,
      'endpoint' => $endpoint,
      'author' => 'common',
      'fields_allowed' => TRUE,
      'content_allowed' => TRUE,
      'shared' => TRUE,
    ]);

    $this->logger->success(strtr('Successfully created node type @id.', [
      '@id' => $id,
    ]));
    return TRUE;
  }

  /**
   * Reset the docstore test users.
   *
   * @command docstore:test-create-users
   * @usage docstore:test-create-users
   *   Create the test users.
   * @validate-module-enabled docstore
   */
  public function createTestUsers() {
    // Base provider.
    $this->createProvider(2, [
      'status' => 1,
      'name' => 'Base provider',
      'type' => 'provider',
      'pass' => '',
      'prefix' => '',
    ]);

    // Test provider 1.
    $this->createProvider(3, [
      'status' => 1,
      'name' => 'Silk test',
      'type' => 'provider',
      'pass' => '',
      'prefix' => 'silk_',
      'api_keys' => 'abcd',
      'api_keys_read_only' => 'xyzzy',
      'dropfolder' => '../drop_folders/silk',
      'shared_secret' => 'verysecret',
    ]);

    // Test provider 2.
    $this->createProvider(4, [
      'status' => 1,
      'name' => 'Another test',
      'type' => 'provider',
      'pass' => '',
      'prefix' => [
        'value' => 'another_',
      ],
      'api_keys' => [
        'value' => 'dcba',
      ],
      'api_keys_read_only' => [
        'value' => 'yzzyx',
      ],
    ]);

    return TRUE;
  }

  /**
   * Create a test file with the given content.  Output the created media uuid.
   *
   * @param string $provider_uuid
   *   Provider uuid.
   * @param string $filename
   *   File name.
   * @param string $content
   *   File content.
   * @param bool $private
   *   Make fike private or not.
   *
   * @command docstore:test-create-file
   * @usage docstore:test-create-file provider uri
   *   Create a file from the uri.
   * @validate-module-enabled docstore
   */
  public function createTestFile($provider_uuid, $filename, $content, $private = FALSE) {
    $provider = $this->loadProvider($provider_uuid);
    $file = $this->createFileEntity($filename, 'undefined', !empty($private), $provider);
    $media = $this->saveFileToDisk($file, $content, $provider, FALSE);
    $this->output()->write($media->uuid());
    return TRUE;
  }

  /**
   * Create a file url hash. Output the hash.
   *
   * @param string $provider_uuid
   *   Provider uuid.
   * @param string $uuid
   *   File/media uuid.
   *
   * @command docstore:test-create-file-url-hash
   * @usage docstore:test-create-file-url-hash provider-uuid file-uuid
   *   Create a hash for the combination of provider and file uuids.
   * @validate-module-enabled docstore
   */
  public function createTestFileUrlHash($provider_uuid, $uuid) {
    $hash = $this->createFileUrlHash($uuid, $this->loadProvider($provider_uuid));
    $this->output()->write($hash);
    return TRUE;
  }

  /**
   * Returns the current user.
   *
   * This for compatibility with the ResourceTrait.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected function currentUser() {
    return $this->currentUser;
  }

  /**
   * Returns the config factory.
   *
   * This for compatibility with the FileTrait.
   *
   * @param string $name
   *   The name of the configuration object to retrieve.
   *
   * @return \Drupal\Core\Config\Config
   *   A configuration object.
   */
  protected function config($name) {
    return $this->configFactory->get($name);
  }

  /**
   * Load a provider.
   *
   * @param string $uuid
   *   Provider uuid.
   *
   * @return \Drupal\user\UserInterface
   *   The provider if found.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   404 Not Found.
   */
  protected function loadProvider($uuid) {
    /** @var \Drupal\user\UserInterface $provider */
    $provider = $this->entityRepository->loadEntityByUuid('user', $uuid);
    if (empty($provider)) {
      throw new NotFoundHttpException('Provider not found');
    }
    return $provider;
  }

  /**
   * Create or reset a test provider.
   *
   * @param int $uid
   *   User id.
   * @param array $data
   *   User data.
   */
  public function createProvider($uid, array $data) {
    $storage = $this->entityTypeManager->getStorage('user');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $user */
    $user = $storage->load($uid) ?? User::create([]);

    foreach ($data as $field => $value) {
      $user->set($field, $value);
    }

    $user->save();
  }

}
