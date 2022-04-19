<?php

namespace Drupal\docstore\Commands;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\State\StateInterface;
use Drupal\docstore\FileTrait;
use Drupal\docstore\ManageFields;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drush\Commands\DrushCommands;
use Symfony\Component\Uid\Uuid;

/**
 * ReliefWeb file migration drush commands.
 *
 * @property \Consolidation\Log\Logger $logger
 */
class DocstoreReliefWebFileMigrationCommands extends DrushCommands {

  use FileTrait;

  /**
   * The docstore database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity field manager.
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
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The memory cache backend.
   *
   * @var Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  protected $memoryCache;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * ReliefWeb provider account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $provider;

  /**
   * The ReliefWeb Drupal 7 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $reliefwebDatabase;

  /**
   * ReliefWeb document type.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $documentType;

  /**
   * {@inheritdoc}
   */
  public function __construct(
      Connection $database,
      EntityFieldManagerInterface $entity_field_manager,
      EntityTypeManagerInterface $entity_type_manager,
      FileSystem $file_system,
      MemoryCacheInterface $memory_cache,
      StateInterface $state
    ) {
    $this->database = $database;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->memoryCache = $memory_cache;
    $this->state = $state;
  }

  /**
   * Run the file migration process.
   *
   * @command docstore:migrate-reliefweb-files
   *
   * @option base_url The base url of the site from which to retrieve the files.
   * @option batch_size Number of reports with attachments to process at once.
   * @option limit Maximum number of reports with non migrated files to process,
   * 0 means process everything.
   * @option source_directory Local source directory with the ReliefWeb files.
   * If empty, fetch the files remotely using the base URL.
   *
   * @default options [
   *   'base_url' => 'https://reliefweb.int',
   *   'batch_size' => 1000,
   *   'limit' => 0,
   *   'source_directory' => '',
   * ]
   * @usage docstore:migrate-reliefweb-files --batch_size=10
   *   Migrate ReliefWeb files (10 documents at a time).
   *
   * @default $options []
   *
   * @validate-module-enabled docstore
   *
   * @aliases ds:mrf
   */
  public function migrate($options = [
    'base_url' => 'https://reliefweb.int',
    'batch_size' => 1000,
    'limit' => 0,
    'source_directory' => '',
  ]) {
    $results = [
      'files' => 0,
      'updated_files' => 0,
      'documents' => 0,
      'updated_documents' => 0,
    ];

    $last_id = $this->getState()->get('docstore_reliefweb_file_migration_last_id', 0);
    $processed = 0;

    $base_url = $options['base_url'];
    $batch_size = (int) $options['batch_size'];
    $limit = (int) $options['limit'];
    $source_directory = rtrim($options['source_directory'], '/');

    if (preg_match('#^https?://[^/]+$#', $base_url) !== 1) {
      $this->logger()->error(strtr('The base url must be in the form http(s)://example.test.'));
      return FALSE;
    }
    if ($batch_size < 1 || $batch_size > 1000) {
      $this->logger()->error(strtr('The batch size must be within 1 and 1000.'));
      return FALSE;
    }
    if ($limit < 0) {
      $this->logger()->error(strtr('The limit must be equal or superior to 0.'));
      return FALSE;
    }
    if (!empty($source_directory) && !file_exists($source_directory)) {
      $this->logger()->error(strtr('The source directory does not exist.'));
      return FALSE;
    }

    while (TRUE) {
      $max_items = $limit > 0 ? min($limit - $processed, $batch_size) : $batch_size;
      $node_data = $this->getNodeData($last_id, $max_items);
      if (empty($node_data)) {
        break;
      }

      $this->logger()->info(strtr('Processing @documents documents...', [
        '@documents' => number_format(count($node_data)),
      ]));

      $data = $this->getEntityDataToMigrate($node_data, $base_url, $source_directory);

      $migrated = $this->migrateEntities($data);

      $this->logger()->info(strtr('Migrated @files files for @documents documents', [
        '@files' => number_format($migrated['updated_files']),
        '@documents' => number_format($migrated['updated_documents']),
      ]));

      $results['files'] += $migrated['files'];
      $results['updated_files'] += $migrated['updated_files'];
      $results['documents'] += $migrated['documents'];
      $results['updated_documents'] += $migrated['updated_documents'];

      foreach ($node_data as $item) {
        if ($item['vid'] > $last_id) {
          $last_id = $item['vid'];
        }
      }

      $processed += count($node_data);
      if ($limit > 0 && $processed >= $limit) {
        break;
      }

      $this->logger()->info(strtr('Progress: @processed...', [
        '@processed' => number_format($processed),
      ]));
    }

    $this->getState()->set('docstore_reliefweb_file_migration_last_id', $last_id);

    $this->logger()->info(strtr('Updated @updated_documents of @documents documents (@updated_files of @files files).', [
      '@updated_documents' => number_format($results['updated_documents']),
      '@documents' => number_format($results['documents']),
      '@updated_files' => number_format($results['updated_files']),
      '@files' => number_format($results['files']),
    ]));

    Cache::invalidateTags(['files']);
    Cache::invalidateTags(['node_list:reliefweb_document']);
  }

  /**
   * Get data of nodes with attachments.
   *
   * @param int $id
   *   Last ID.
   * @param int $limit
   *   Number of IDS to retrieve.
   *
   * @return array
   *   Associative array keyed by node ids and with the title and status as
   *   values.
   */
  protected function getNodeData($id = 0, $limit = 1000) {
    $query = $this->getReliefwebDatabase()
      ->select('node', 'n')
      ->fields('n', ['nid', 'vid', 'title', 'status'])
      ->condition('n.type', 'report', '=');

    if ($id !== 0) {
      $query->condition('n.vid', $id, '>');
    }

    $query->innerJoin('field_data_field_file', 'f', 'f.entity_id = n.nid');
    $query->condition('f.entity_type', 'node', '=');
    $query->condition('f.field_file_fid', NULL, 'IS NOT NULL');

    return $query
      ->groupBy('n.nid')
      ->orderBy('n.vid', 'ASC')
      ->range(0, $limit)
      ->execute()
      ?->fetchAllAssoc('nid', \PDO::FETCH_ASSOC) ?? [];
  }

  /**
   * Migrate the given documnent and file entities.
   *
   * @param array $entities
   *   Entity data grouped by report ID.
   */
  protected function migrateEntities(array $entities) {
    $count_documents = count($entities);
    $count_files = 0;
    $count_updated_documents = 0;
    $count_updated_files = 0;

    foreach ($entities as $data) {
      $update = FALSE;
      foreach ($data['files'] ?? [] as $file) {
        switch ($file['action']) {
          case 'none':
            // Nothing to do, skip.
            break;

          case 'new':
            $this->createMedia($file);
            $update = TRUE;
            break;

          case 'new revision':
            $this->updateMedia($file);
            $update = TRUE;
            break;

          case 'update status':
            $this->updateMediaStatus($file);
            break;
        }
      }

      if ($update) {
        $this->updateDocument($data);
        $count_updated_documents++;
        $count_updated_files += count($data['files']);
      }
      $count_files += isset($data['files']) ? count($data['files']) : 0;
    }

    $this->resetStaticCaches();

    return [
      'documents' => $count_documents,
      'files' => $count_files,
      'updated_documents' => $count_updated_documents,
      'updated_files' => $count_updated_files,
    ];
  }

  /**
   * Update a ReliefWeb document.
   *
   * @param array $data
   *   Document data with title, status and files.
   */
  protected function updateDocument(array $data) {
    $document_type = $this->getDocumentType();

    $uuid = static::generateDocumentUuid($data['nid']);

    $document = $this->loadEntityByUuid('node', $uuid);
    if (empty($document)) {
      $document = Node::create([
        'uuid' => $uuid,
        'type' => $document_type->id(),
        'title' => $data['title'],
        'uid' => $this->getProviderId(),
        'author' => 'reliefweb',
        'files' => [],
        'private' => TRUE,
        'status' => Node::PUBLISHED,
      ]);
    }

    $document->set('files', array_keys($data['files']));
    $document->setNewRevision(FALSE);
    $document->save();
  }

  /**
   * Get the ReliefWeb document type.
   *
   * @return \Drupal\node\Entity\NodeType
   *   The created document type.
   */
  protected function getDocumentType() {
    if (!isset($this->documentType)) {
      $this->documentType = $this->getEntityTypeManager()
        ->getStorage('node_type')
        ->load('reliefweb_document');
      if (empty($this->documentType)) {
        $this->documentType = $this->createDocumentType();
      }
      // Ensure the index is disabled.
      $index = Index::load('documents_reliefweb_document');
      if (isset($index)) {
        $this->logger()->info('Disabling index');
        $index->disable();
        $index->save();
      }
    }
    return $this->documentType;
  }

  /**
   * Create the ReliefWeb document type.
   *
   * @return \Drupal\node\Entity\NodeType
   *   The created document type.
   */
  protected function createDocumentType() {
    $manager = new ManageFields(
      $this->getProvider(),
      'reliefweb_document',
      $this->getEntityFieldManager(),
      $this->getEntityTypeManager(),
      $this->getDatabase()
    );

    $type = $manager->createDocumentType([
      'label' => 'reliefweb_document',
      'machine_name' => 'reliefweb_document',
      'endpoint' => 'reliefweb-document',
      'author' => 'reliefweb',
      'shared' => FALSE,
      'content_allowed' => FALSE,
      'fields_allowed' => FALSE,
      'allow_duplicates' => TRUE,
      'use_revisions' => FALSE,
    ]);

    $this->rebuildEndpoints();

    return $type;
  }

  /**
   * Rebuild endpoints state.
   */
  public function rebuildEndpoints() {
    $key = 'docstore.endpoints.document_types';

    $node_types = $this->getEntityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($node_types as $node_type) {
      $endpoint = $node_type->getThirdPartySetting('docstore', 'endpoint');
      $endpoints[$endpoint] = $node_type->id();
    }

    $this->getState()->set($key, $endpoints);
  }

  /**
   * Create a media resource from the given file data.
   *
   * @param array $data
   *   File data.
   */
  protected function createMedia(array $data) {
    $file = $this->createFile($data);

    // Create a new media entity.
    $media = Media::create([
      'uuid' => $data['uuid'],
      'bundle' => 'file',
      'uid' => $this->getProviderId(),
      'name' => $file->getFileName(),
      'status' => TRUE,
      'field_media_file' => [
        'target_id' => $file->id(),
      ],
    ]);

    // Save as a new revision.
    $media->setRevisionCreationTime(time());
    $media->setRevisionLogMessage('File created');
    $media->setRevisionUserId($this->getProviderId());
    $media->setNewRevision(FALSE);
    $media->isDefaultRevision(TRUE);
    $media->save();

    // @todo copy the file and create the symlink.
    if ($this->downloadFile($data, $file)) {
      $this->regenerateMediaSymlinks($media);
    }
  }

  /**
   * Create a media resource from the given file data.
   *
   * @param array $data
   *   File data.
   */
  protected function updateMedia(array $data) {
    $file = $this->createFile($data);

    // Load the media entity.
    $media = $this->loadEntityByUuid('media', $data['uuid']);

    // Reference the newly created file.
    $media->field_media_file->target_id = $file->id();

    // Save as a new revision.
    $media->setRevisionCreationTime(time());
    $media->setRevisionLogMessage('File updated');
    $media->setRevisionUserId($this->getProviderId());
    $media->setNewRevision(TRUE);
    $media->isDefaultRevision(TRUE);
    $media->save();

    // @todo copy the file and create the symlink.
    if ($this->downloadFile($data, $file, $source_directory)) {
      $this->regenerateMediaSymlinks($media);
    }
  }

  /**
   * Create a file entity from the given data.
   *
   * @param array $data
   *   File data.
   *
   * @return \Drupal\file\FileInterface
   *   File entity.
   */
  protected function createFile(array $data) {
    $file = File::create(['uuid' => $data['file_uuid']]);
    $file->setOwnerId($this->getProviderId());
    $file->setMimeType($data['filemime']);
    $file->setFileName($data['filename']);
    $file->setSize($data['filesize']);
    $file->setFileUri($data['uri']);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Update the media status.
   *
   * @param array $data
   *   File data.
   */
  protected function updateMediaStatus(array $data) {
    $media = $this->loadEntityByUuid('media', $data['uuid']);
    if (empty($media)) {
      $this->logger()->error(strtr('Enable to find media @uuid', [
        '@uuid' => $data['uuid'],
      ]));
    }
    else {
      try {
        $this->moveMediaFiles($media, $data['private']);
      }
      catch (\Exception $exception) {
        $this->logger()->error(strtr('Enable to move media @uuid: @error', [
          '@uuid' => $data['uuid'],
          '@error' => $exception->getMessage(),
        ]));
      }
    }
  }

  /**
   * Download file.
   *
   * @param array $data
   *   File data with source URL.
   * @param \Drupal\file\Entity\File $file
   *   File entity with destination URI.
   *
   * @return bool
   *   TRUE if the upload was successful.
   */
  protected function downloadFile(array $data, File $file) {
    $url = $data['url'];
    $uri = $file->getFileUri();

    // Try to download the file.
    $success = FALSE;
    if ($this->prepareDirectory($uri)) {
      if (!copy($url, $uri)) {
        $this->logger()->error(strtr('Unable to download file @url to @uri.', [
          '@url' => $url,
          '@uri' => $uri,
        ]));
      }
      else {
        $this->logger()->info(strtr('Successfully downloaded file @url to @uri.', [
          '@url' => $url,
          '@uri' => $uri,
        ]));
        $success = TRUE;
      }
    }
    else {
      $this->logger()->error(strtr('Unable to create directory for @uri.', [
        '@uri' => $uri,
      ]));
    }

    return $success;
  }

  /**
   * Prepare a directory, creating unexisting paths.
   *
   * @param string $uri
   *   File or directory URI.
   *
   * @return bool
   *   TRUE if the preparation succeeded.
   */
  public function prepareDirectory($uri) {
    $file_system = $this->getFileSystem();
    $directory = $file_system->dirname($uri);
    return $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
  }

  /**
   * Get the file attachments for the given node ids.
   *
   * @param array $node_data
   *   Node data keyed by ids.
   * @param string $base_url
   *   The base url of the site from which to retrieve the files.
   * @param string $source_directory
   *   The source directory with the ReliefWeb files.
   *
   * @return array
   *   Associative array keyed by node ids and with their attachments' data as
   *   values.
   */
  protected function getEntityDataToMigrate(array $node_data, $base_url, $source_directory) {
    $query = $this->getReliefwebDatabase()
      ->select('field_data_field_file', 'f')
      ->fields('f', ['entity_id', 'delta', 'field_file_description'])
      ->condition('f.entity_type', 'node', '=')
      ->condition('f.entity_id', array_keys($node_data), 'IN');

    // Join the file table to retrieve the file uri, name and mime type.
    $query->innerJoin('file_managed', 'fm', 'fm.fid = f.field_file_fid');
    $query->fields('fm', ['fid', 'uri', 'filename', 'filemime', 'filesize']);

    $records = $query
      ->orderBy('f.entity_id', 'ASC')
      ->orderBy('f.delta', 'ASC')
      ->execute()
      ?->fetchAll(\PDO::FETCH_ASSOC) ?? [];

    // Group the files per entity.
    $files = [];
    foreach ($records as $record) {
      $record['private'] = empty($node_data[$record['entity_id']]['status']);
      $file = $this->prepareFileData($record, $base_url, $source_directory);
      $files[$file['uuid']] = $file;
    }

    // Determine which action to take for the files.
    $files = $this->checkFiles($files);

    // Group the files by node id.
    foreach ($files as $uuid => $file) {
      $node_data[$file['entity_id']]['files'][$uuid] = $file;
    }
    return $node_data;
  }

  /**
   * Determine which action to take for the files.
   *
   * @param array $files
   *   File data.
   *
   * @return array
   *   File data with an additional "action" key for each item that can be:
   *   - new: if a new file and media should be created
   *   - new revision: if a new revision on the media should be created
   *   - update status:  if the file and media status (private <-> public)
   *   - none: if no action should be taken.
   */
  protected function checkFiles(array $files) {
    $query = $this->getDatabase()->select('media', 'm');
    $query->fields('m', ['uuid']);
    $query->condition('m.uuid', array_keys($files), 'IN');
    $query->leftJoin('media__field_media_file', 'f', 'f.entity_id = m.mid');
    $query->leftJoin('file_managed', 'fm', 'fm.fid = f.field_media_file_target_id');
    $query->addField('fm', 'uuid', 'file_uuid');
    $query->addField('fm', 'uri', 'uri');

    $items = $query
      ->execute()
      ?->fetchAllAssoc('uuid', \PDO::FETCH_ASSOC) ?? [];

    foreach ($files as $uuid => $file) {
      // File resource already exists.
      if (isset($items[$uuid])) {
        $item = $items[$uuid];

        // Check changes to the underlying file.
        if ($item['file_uuid'] === $file['file_uuid']) {
          // URI changed (private <-> public).
          if ($item['uri'] !== $file['uri']) {
            $files[$uuid]['action'] = 'update status';
          }
          // No change.
          else {
            $files[$uuid]['action'] = 'none';
          }
        }
        // File was changed -> create a new revision.
        else {
          $files[$uuid]['action'] = 'new revision';
        }
      }
      // A new file resource needs to be created.
      else {
        $files[$uuid]['action'] = 'new';
      }
    }

    return $files;
  }

  /**
   * Prepare the D9 file data from the D7 database record.
   *
   * @param array $record
   *   D7 file record.
   * @param string $base_url
   *   The base url of the site from which to retrieve the files.
   * @param string $source_directory
   *   The source directory with the ReliefWeb files.
   *
   * @return array
   *   D9 file data.
   */
  protected function prepareFileData(array $record, $base_url, $source_directory) {
    $uuid = static::generateAttachmentUuid($record['uri']);
    $file_uuid = static::generateAttachmentFileUuid($uuid, $record['fid']);
    $extension = mb_strtolower(pathinfo($record['filename'], PATHINFO_EXTENSION));
    $private = !empty($record['private']);

    $uri = $private ? 'private://' : 'public://';
    $uri .= 'files/';
    $uri .= substr($file_uuid, 0, 2);
    $uri .= '/' . substr($file_uuid, 2, 2);
    $uri .= '/' . $file_uuid . '.' . $extension;

    if (empty($source_directory)) {
      $url = static::getFileLegacyUrl($record['uri'], $base_url);
    }
    else {
      $url = static::getFileLegacyPath($record['uri'], $source_directory);
    }

    return [
      'fid' => $record['fid'],
      'filename' => $record['filename'],
      'filemime' => $record['filemime'],
      'filesize' => $record['filesize'],
      'uri' => $uri,
      'uuid' => $uuid,
      'file_uuid' => $file_uuid,
      'url' => $url,
      'legacy_uri' => $record['uri'],
      'private' => $private,
      'entity_id' => $record['entity_id'],
    ];
  }

  /**
   * Reset the static caches to try to free some memory.
   */
  protected function resetStaticCaches() {
    $this->memoryCache->deleteAll();
  }

  /**
   * Get the ReliefWeb provider account ID.
   *
   * @return int
   *   ReliefWeb provider account ID.
   */
  protected function getProviderId() {
    return $this->getProvider()->id();
  }

  /**
   * Get the ReliefWeb provider account.
   *
   * @return \Drupal\user\UserInterface
   *   ReliefWeb provider account.
   */
  protected function getProvider() {
    if (!isset($this->provider)) {
      $entities = $this->getEntityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['name' => 'reliefweb']);
      $this->provider = reset($entities);
    }
    return $this->provider;
  }

  /**
   * Get the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  protected function getDatabase() {
    return $this->database;
  }

  /**
   * Get the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function getEntityFieldManager() {
    return $this->entityFieldManager;
  }

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Get the file system.
   *
   * @return \Drupal\Core\File\FileSystem
   *   The file system.
   */
  protected function getFileSystem() {
    return $this->fileSystem;
  }

  /**
   * Get the state service.
   *
   * @return \Drupal\Core\State\StateInterface
   *   The state service.
   */
  protected function getState() {
    return $this->state;
  }

  /**
   * Get the reliefweb database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The reliefweb database connection.
   */
  protected function getReliefwebDatabase() {
    if (!isset($this->reliefwebDatabase)) {
      $this->reliefwebDatabase = Database::getConnection('default', 'rwint7');
    }
    return $this->reliefwebDatabase;
  }

  /**
   * Load an entity by UUID.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   * @param string $uuid
   *   Entity UUID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Loaded entity.
   */
  protected function loadEntityByUuid($entity_type_id, $uuid) {
    $entities = $this->getEntityTypeManager()
      ->getStorage($entity_type_id)
      ->loadByProperties(['uuid' => $uuid]);
    return reset($entities);
  }

  /**
   * Get the preview URL for the file record.
   *
   * @param array $record
   *   D7 file record.
   * @param string $base_url
   *   The base url of the site from which to retrieve the files.
   *
   * @return string
   *   D7 preview URL.
   */
  protected static function getFilePreviewUrl(array $record, $base_url) {
    if (preg_match('/\|\d+\|(0|90|-90)$/', $record['field_file_description']) === 1) {
      $filename = basename(urldecode($record['filename']), '.pdf');
      $filename = str_replace('%', '', $filename);
      $filename = UrlHelper::encodePath($record['fid'] . '-' . $filename . '.png');
      return $base_url . '/sites/reliefweb.int/files/resources-pdf-previews/' . $filename;
    }
    return '';
  }

  /**
   * Generate an attachment's UUID from it's old URI on reliefweb.int.
   *
   * @param string $uri
   *   File URI (ex: public://resources/my.pdf).
   *
   * @return string
   *   The attachment UUID.
   */
  protected static function generateAttachmentUuid($uri) {
    // Strip the '%' characters for compatibility with the preview URLs.
    $uri = str_replace('%', '', $uri);

    // Replace the public scheme with the actual reliefweb.int base public file
    // URI so that it's unique.
    $uuid_uri = str_replace('public://', 'https://reliefweb.int/sites/reliefweb.int/files/', $uri);

    // Generate the UUID based on the URI.
    return Uuid::v3(Uuid::fromString(Uuid::NAMESPACE_URL), $uuid_uri)->toRfc4122();
  }

  /**
   * Generate a UUID from the attachment UUID and the file ID.
   *
   * @param string $uuid
   *   The attachment UUID.
   * @param string $fid
   *   The file ID.
   */
  protected static function generateAttachmentFileUuid($uuid, $fid) {
    return Uuid::v3(Uuid::fromString($uuid), $fid)->toRfc4122();
  }

  /**
   * Generate the UUID of a document resource from a node id.
   *
   * @param int $nid
   *   Node ID.
   *
   * @return string
   *   Document UUID.
   */
  protected static function generateDocumentUuid($nid) {
    // Permanent URI for the node. This will be used to create a document
    // resource in the docstore with the same UUID.
    $uuid_uri = 'https://reliefweb.int/node/' . $nid;

    // Generate the UUID based on the URI.
    return Uuid::v3(Uuid::fromString(Uuid::NAMESPACE_URL), $uuid_uri)->toRfc4122();
  }

  /**
   * Get a file legacy URL from it's legacy URI.
   *
   * @param string $uri
   *   File URI.
   * @param string $base_url
   *   The base url of the legacy URL in the form http(s)://example.test.
   *
   * @return string
   *   File URL.
   */
  protected static function getFileLegacyUrl($uri, $base_url = 'https://reliefweb.int') {
    $filename = UrlHelper::encodePath(basename($uri));
    return $base_url . '/sites/reliefweb.int/files/resources/' . $filename;
  }

  /**
   * Get a file legacy path from it's legacy URI.
   *
   * @param string $uri
   *   File URI.
   * @param string $source_directory
   *   The directory with the ReliefWeb files.
   *
   * @return string
   *   File Path.
   */
  protected static function getFileLegacyPath($uri, $source_directory) {
    return $source_directory . '/resources/' . basename($uri);
  }

}
