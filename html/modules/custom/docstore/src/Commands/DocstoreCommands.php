<?php

namespace Drupal\docstore\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drupal\Component\Utility\Unicode;
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
use Drupal\docstore\FileTrait;
use Drupal\docstore\ManageFields;
use Drupal\docstore\ResourceTrait;
use Drupal\docstore\RevisionableResourceTrait;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\node\Entity\Node;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;
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
  use RevisionableResourceTrait;

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
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

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
      State $state,
      ClientInterface $httpClient
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
    $this->httpClient = $httpClient;
  }

  /**
   * Add HRinfo ids to locations where necessary.
   *
   * Some terms were imported from HRinfo without an id. This fixes that.
   *
   * @command docstore:fix-location-ids
   * @usage docstore:fix-location-ids
   *   Add location ids where they're missing.
   * @validate-module-enabled docstore
   */
  public function fixLocationIds() {
    $query = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', 'locations')
      ->condition('id', NULL, 'IS NULL');
    $tids_with_missing_id_value = $query->execute();
    $counter = 0;

    foreach ($tids_with_missing_id_value as $tid) {
      $this->logger->info('Processing ' . $tid);

      // Load term.
      $term = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->load($tid);

      // Get an assessment tagged with the location.
      $query = $this->database->select('taxonomy_index', 'ti');
      $query->fields('ti', ['nid']);
      $query->condition('ti.tid', $tid);
      $nids = $query->execute()->fetchAssoc();
      $nid = reset($nids);

      // Fetch assessment details from HRInfo.
      $node = $this->entityTypeManager
        ->getStorage('node')
        ->load($nid);
      if (empty($node)) {
        $this->logger->error('Failed to load node for nid ' . $nid);
        continue;
      }
      $url = 'https://www.humanitarianresponse.info/en/api/v1.0/assessments/' . $node->get('id')->value;
      $response = $this->httpClient->request('GET', $url);
      if ($response->getStatusCode() !== 200) {
        $this->logger->error('Failed to get api response for ' . $url);
        return;
      }

      $raw = $response->getBody()->getContents();
      $data = json_decode($raw);
      $row = $data->data[0];

      foreach ($row->locations as $location) {
        if ($location->label == $term->getName()) {
          $term->set('id', $location->id);
          $term->save();
          continue;
        }
      }
      $counter++;
      if ($counter % 50 === 0) {
        $this->logger->info("Progress: $counter locations updated.");
      }
    }
    $this->logger->info("Finished: $counter locations updated.");
  }

  /**
   * Update locations.
   *
   * @param int $page_number
   *   HRinfo api page number - useful for picking up again after a time out.
   * @param string $url
   *   HRinfo api url for next set of results.
   *
   * @command docstore:update-locations
   * @usage docstore:update-locations
   *   Update locations from HRinfo api.
   * @validate-module-enabled docstore
   */
  public function updateLocations($page_number = 0, $url = '') {

    if (empty($url)) {
      $url = 'https://www.humanitarianresponse.info/en/api/v1.0/locations';
      if ($page_number > 0) {
        $url .= '?page[number]=' . $page_number;
      }
    }

    // Get vocabulary fields.
    $fields = $this->entityFieldManager
      ->getFieldDefinitions('taxonomy_term', 'locations');

    // Load provider.
    $provider = $this->entityTypeManager->getStorage('user')->load(2);

    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() !== 200) {
      $this->logger->error('Failed to get api response for ' . $url);
      return;
    }
    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $term = NULL;
      $parent = NULL;
      $possible_terms = NULL;
      $possible_parents = NULL;

      $possible_terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->getQuery()
        ->condition('vid', 'locations')
        ->condition('id', $row->id)
        ->execute();
      if (!empty($possible_terms)) {
        $term = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->load(reset($possible_terms));
      }

      $display_name = $row->label;
      if (!empty($row->parent) && !empty($row->parent[0]) && isset($row->parent[0]->id)) {
        $possible_parents = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->getQuery()
          ->condition('vid', 'locations')
          ->condition('id', $row->parent[0]->id)
          ->execute();
        if (!empty($possible_parents)) {
          $parent = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->load(reset($possible_parents));
        }
        if (!empty($parent->display_name->value)) {
          $parent_display_name = $parent->display_name->value;
          $display_name = $parent_display_name . ' > ' . $display_name;
        }
      }

      if (empty($term)) {
        $item = [
          'name' => $row->label,
          'display_name' => $display_name,
          'vid' => 'locations',
          'created' => [],
          'provider_uuid' => [],
          'parent' => [],
          'description' => '',
        ];

        // Set creation time.
        $item['created'][] = [
          'value' => time(),
        ];

        // Set parent.
        if (!empty($parent)) {
          $item['parent'][] = [
            'target_uuid' => $parent->id(),
          ];
        }

        // Set owner.
        $item['provider_uuid'][] = [
          'target_uuid' => $provider->uuid(),
        ];

        $item['author'][] = [
          'value' => 'Shared',
        ];

        $term = Term::create($item);
      }

      foreach (array_keys($fields) as $name) {
        $field_name = str_replace('-', '_', $name);

        // Handle special cases.
        if ($field_name == 'id') {
          $term->set($field_name, $row->id);
          continue;
        }
        if ($field_name == 'hrinfo_id') {
          $term->set($field_name, $row->id);
          continue;
        }
        if ($field_name == 'parent') {
          if (!empty($parent)) {
            $term->set($field_name, $parent->id());
          }
          continue;
        }
        if ($field_name == 'changed') {
          continue;
        }
        if ($field_name == 'created') {
          continue;
        }
        if ($field_name == 'display_name') {
          $term->set($field_name, $display_name);
          continue;
        }
        if ($field_name == 'admin_level') {
          $levels = count($row->parents);
          if ($levels > 0) {
            $term->set($field_name, $levels - 1);
          }
          continue;
        }
        if ($field_name == 'geolocation') {
          if (isset($row->geolocation) && isset($row->geolocation->lat)) {
            $term->set($field_name, 'POINT (' . $row->geolocation->lon . ' ' . $row->geolocation->lat . ')');
          }
          continue;
        }

        if ($term->hasField($field_name)) {
          $value = FALSE;
          if (empty($row->{$name})) {
            continue;
          }
          else {
            if ($term->{$field_name}->value != $row->{$name}) {
              $value = $row->{$name};
            }
          }

          if (!empty($value)) {
            $term->set($field_name, $value);
          }
        }
      }

      $violations = $term->validate();
      if (count($violations) > 0) {
        $this->logger->info($violations->get(0)->getMessage());
        $this->logger->info($violations->get(0)->getPropertyPath());
      }
      else {
        $term->save();
      }
    }

    // Check for more data.
    if (isset($data->next) && isset($data->next->href)) {
      $this->logger->info("Next page:");
      $this->logger->info($data->next->href);
      $this->updateLocations(0, $data->next->href);
    }
  }

  /**
   * Update disasters.
   *
   * @param int $days_ago
   *   When to start updates from.
   * @param string $date
   *   ATOM format date for querying RWapi.
   * @param string $url
   *   RWapi url for next set of results.
   *
   * @command docstore:update-disasters
   * @usage docstore:update-disasters 7
   *   From RWapi, add any disasters created in the past 7 days.
   * @validate-module-enabled docstore
   */
  public function updateDisasters($days_ago = 0, $date = '', $url = '') {
    if (empty($date)) {
      if (empty($days_ago)) {
        $days_ago = 100;
      }
      $date = date(DATE_ATOM, mktime(0, 0, 0, date('m'), date('d') - $days_ago, date('Y')));
    }

    // Load provider.
    $provider = $this->entityTypeManager->getStorage('user')->load(2);

    if (empty($url)) {
      $url = 'https://api.reliefweb.int/v1/disasters?appname=vocabulary&preset=external&limit=100';
      $url .= '&fields[include][]=country.iso3';
      $url .= '&fields[include][]=primary_country.iso3';
      $url .= '&fields[include][]=profile.overview';
      $url .= '&fields[include][]=description';
      $url .= '&fields[include][]=type.code';
      $url .= '&fields[include][]=primary_type.code';
      $url .= '&fields[include][]=glide';
      $url .= '&filter[field]=date.created';
      $url .= '&filter[value][from]=' . urlencode($date);
    }

    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() !== 200) {
      $this->logger->info('Failed to get api response.');
    }
    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $node = NULL;
      $possible_nodes = NULL;
      $possible_nodes = $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['type' => 'disaster', 'id' => $row->id]);
      $node = reset($possible_nodes);

      if (empty($node)) {
        $item = [
          'title' => $row->fields->name,
          'type' => 'disaster',
          'created' => [],
          'provider_uuid' => [],
          'description' => '',
        ];

        // Set owner.
        $item['provider_uuid'][] = [
          'target_uuid' => $provider->uuid(),
        ];

        // Set creation time.
        $item['created'][] = [
          'value' => time(),
        ];

        $item['author'][] = [
          'value' => 'Shared',
        ];
        $node = Node::create($item);
      }

      $node->set('title', $row->fields->name);
      $node->set('files', []);

      // Id.
      $node->set('id', $row->fields->id);

      // Status.
      // Needs to create terms if they don't already exist.
      $status_term = NULL;
      $status_term = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $row->fields->status]);
      if (empty($status_term)) {
        // Create it.
        $item = [
          'name' => $row->fields->status,
          'vid' => 'disaster_status',
          'created' => [],
          'provider_uuid' => [],
          'description' => '',
        ];

        // Set creation time.
        $item['created'][] = [
          'value' => time(),
        ];

        // Set owner.
        $item['provider_uuid'][] = [
          'target_uuid' => $provider->uuid(),
        ];

        $item['author'][] = [
          'value' => 'Shared',
        ];

        $status_term = Term::create($item);
        $status_term->save();
        $status_tid = $status_term->id();
      }
      else {
        $status_tid = reset($status_term)->id();
      }
      $node->set('disaster_status', ['target_id' => $status_tid]);

      // Glide.
      if (isset($row->fields->glide)) {
        $node->set('glide', $row->fields->glide);
      }

      // Profile.
      if (isset($row->fields->profile->overview)) {
        $node->set('profile', $row->fields->profile->overview);
      }

      // Description.
      if (isset($row->fields->description)) {
        $node->set('description', $row->fields->description);
      }

      // Disaster type.
      if (isset($row->fields->type) && !empty($row->fields->type)) {
        $type_data = [];
        foreach ($row->fields->type as $type) {
          $query = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->getQuery()
            ->condition('vid', 'disaster_types')
            ->condition('common_disaster_type_code', $type->code);
          $term_ids = $query->execute();
          $term_id = reset($term_ids);
          if (!empty($term_id)) {
            $type_data[] = [
              'target_id' => $term_id,
            ];
          }
        }
        $node->set('disaster_type', $type_data);
      }

      // Primary disaster type.
      if (isset($row->fields->primary_type) && !empty($row->fields->primary_type)) {
        $query = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->getQuery()
          ->condition('vid', 'disaster_types')
          ->condition('common_disaster_type_code', $row->fields->primary_type->code);
        $term_ids = $query->execute();
        $term_id = reset($term_ids);
        if (!empty($term_id)) {
          $type = ['target_id' => $term_id];
          $node->set('primary_disaster_type', $type);
        }
      }

      // Countries.
      if (isset($row->fields->country) && !empty($row->fields->country)) {
        $country_data = [];
        foreach ($row->fields->country as $country) {
          $query = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->getQuery()
            ->condition('vid', 'countries')
            ->condition('common_iso3', $country->iso3);
          $term_ids = $query->execute();
          $term_id = reset($term_ids);
          if (!empty($term_id)) {
            $country_data[] = [
              'target_id' => $term_id,
            ];
          }
        }
        $node->set('countries', $country_data);
      }

      // Primary country.
      if (isset($row->fields->primary_country) && !empty($row->fields->primary_country)) {
        $query = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->getQuery()
          ->condition('vid', 'countries')
          ->condition('common_iso3', $row->fields->primary_country->iso3);
        $term_ids = $query->execute();
        $term_id = reset($term_ids);
        if (!empty($term_id)) {
          $country = ['target_id' => $term_id];
          $node->set('primary_country', $country);
        }
      }

      $node->set('author', 'Shared');

      $violations = $node->validate();
      if (count($violations) > 0) {
        $this->logger->info($violations->get(0)->getMessage());
        $this->logger->info($violations->get(0)->getPropertyPath());
      }
      else {
        $node->save();
      }
    }

    // Check for more data.
    if (isset($data->links) && isset($data->links->next->href)) {
      $this->logger->info('Next page:');
      $this->logger->info($data->links->next->href);
      $this->updateDisasters(0, $date, $data->links->next->href);
    }
  }

  /**
   * Update disaster_types.
   *
   * @command docstore:update-disaster-types
   * @usage docstore:update-disaster-types
   *   Update countries from RWapi.
   * @validate-module-enabled docstore
   */
  public function updateDisasterTypes() {

    $url = 'https://api.reliefweb.int/v1/references/disaster-types?appname=vocabulary';

    // Load provider.
    $provider = $this->entityTypeManager
      ->getStorage('user')->load(2);

    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() !== 200) {
      $this->logger->info('Failed to get api response.');
    }
    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $terms = taxonomy_term_load_multiple_by_name($row->fields->name, 'disaster_types');
      if (!$terms) {
        $item = [
          'name' => $row->fields->name,
          'vid' => 'disaster_types',
          'created' => [],
          'provider_uuid' => [],
          'parent' => [],
          'description' => '',
        ];

        // Set creation time.
        $item['created'][] = [
          'value' => time(),
        ];

        // Set owner.
        $item['provider_uuid'][] = [
          'target_uuid' => $provider->uuid(),
        ];

        $item['author'][] = [
          'value' => 'Shared',
        ];

        $term = Term::create($item);
      }
      else {
        $term = reset($terms);
      }

      $fields = [
        'id' => 'common_id',
        'name' => 'name',
        'code' => 'common_disaster_type_code',
        'description' => 'description',
      ];

      foreach ($fields as $name => $field_name) {
        if ($term->hasField($field_name)) {
          $value = FALSE;
          if (isset($row->fields->{$name})) {
            $value = $row->fields->{$name};
          }

          $term->set($field_name, $value);
        }
      }

      $violations = $term->validate();
      if (count($violations) > 0) {
        $this->logger->info($violations->get(0)->getMessage());
        $this->logger->info($violations->get(0)->getPropertyPath());
      }
      else {
        $term->save();
      }
    }
  }

  /**
   * Update countries.
   *
   * @command docstore:update-countries
   * @usage docstore:update-countries
   *   Update countries from taas site.
   * @validate-module-enabled docstore
   */
  public function updateCountries() {

    $field_map = [
      'common_admin_level' => 'admin_level',
      'common_dgacm_list' => 'dgacm_list',
      'common_fts_api_id' => 'fts_api_id',
      'common_hrinfo_id' => 'hrinfo_id',
      'common_id' => 'id',
      'common_iso2' => 'iso2',
      'common_iso3' => 'iso3',
      'common_m49' => 'm49',
      'common_regex' => 'regex',
      'common_reliefweb_id' => 'reliefweb_id',
      'common_unterm_list' => 'unterm-list',
      'common_x_alpha_2' => 'x-alpha-2',
      'common_x_alpha_3' => 'x-alpha-3',
    ];

    $url = 'https://vocabulary.unocha.org/json/beta-v3/countries.json';

    // Load provider.
    $provider = $this->entityTypeManager
      ->getStorage('user')->load(2);

    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() === 200) {
      $raw = $response->getBody()->getContents();
      $data = json_decode($raw);

      foreach ($data->data as $row) {
        $term = taxonomy_term_load_multiple_by_name($row->label->default, 'countries');
        if (!$term) {
          $item = [
            'name' => $row->label->default,
            'vid' => 'countries',
            'created' => [],
            'provider_uuid' => [],
            'parent' => [],
            'description' => '',
          ];

          // Set creation time.
          $item['created'][] = [
            'value' => time(),
          ];

          // Set owner.
          $item['provider_uuid'][] = [
            'target_uuid' => $provider->uuid(),
          ];

          $item['author'][] = [
            'value' => 'Shared',
          ];

          $term = Term::create($item);
        }
        else {
          $term = reset($term);
        }

        $fields = $this->entityFieldManager
          ->getFieldDefinitions('taxonomy_term', 'countries');
        foreach (array_keys($fields) as $name) {
          $value = NULL;
          if ($name == 'name') {
            $term->set($name, $row->label->default);
          }
          if ($name == 'common_geolocation') {
            if (isset($row->geolocation) && isset($row->geolocation->lat)) {
              $term->set($name, 'POINT (' . $row->geolocation->lon . ' ' . $row->geolocation->lat . ')');
            }
            continue;
          }
          if ($name === 'common_territory') {
            $territory_term = $this->createTerritoryTerms($row);
            if ($territory_term) {
              $term->set($name, ['target_id' => $territory_term->id()]);
            }
            continue;
          }
          $field_name = '';
          if (isset($field_map[$name])) {
            $field_name = $field_map[$name];
          }
          else {
            continue;
          }

          if (isset($row->{$field_name})) {
            $value = $row->{$field_name};
          }

          if ($field_name == 'unterm-list' || $field_name == 'dgacm-list') {
            if ($value === 'Y') {
              $value = TRUE;
            }
            else {
              $value = FALSE;
            }
          }

          if ($value !== NULL) {
            $term->set($name, $value);
          }
        }

        $violations = $term->validate();
        if (count($violations) > 0) {
          $this->logger->info($violations->get(0)->getMessage());
          $this->logger->info($violations->get(0)->getPropertyPath());
        }
        else {
          $term->save();
        }
      }
    }
  }

  /**
   * Sync all from HRInfo.
   *
   * @command docstore:hrinfo-sync-all
   * @usage docstore:hrinfo-sync-all
   *   Sync all from HRInfo.
   * @validate-module-enabled docstore
   */
  public function updateFromHrInfo() {
    $this->updateOrganizations();
    $this->updateOperations();
    $this->updateClusters();
  }

  /**
   * Update clusters.
   *
   * @command docstore:update-clusters
   * @usage docstore:update-clusters
   *   Update clusters from HRInfo.
   * @validate-module-enabled docstore
   */
  public function updateClusters($url = '') {
    if (empty($url)) {
      $ts = $this->state->get('docstore_sync_local_groups_ts', 1641025083);
      $url = 'https://www.humanitarianresponse.info/en/api/v1.0/bundles';
      $url .= '?filter[changed][value]=' . $ts . '&filter[changed][operator]=>';
      $url .= '&sort=changed,id';
    }

    // Load provider.
    $provider = $this->entityTypeManager->getStorage('user')->load(2);

    // Load vocabulary.
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load('local_coordination_groups');

    // Vocabulary fields.
    $fields = [
      'id' => 'string',
      'email' => 'string',
      'website' => 'string',
      'display_name' => 'string',
      'global_cluster' => [
        'type' => 'term_reference',
        'target' => 'global_coordination_groups',
        'multiple' => FALSE,
      ],
      'lead_agencies' => [
        'type' => 'term_reference',
        'target' => 'organizations',
        'multiple' => TRUE,
      ],
      'partners' => [
        'type' => 'term_reference',
        'target' => 'organizations',
        'multiple' => TRUE,
      ],
      'operations' => [
        'type' => 'term_reference',
        'target' => 'operations',
        'multiple' => TRUE,
      ],
      'ngo_participation' => 'boolean',
      'government_participation' => 'boolean',
      'inter_cluster' => 'boolean',
    ];

    // Load provider.
    $provider = $this->entityTypeManager->getStorage('user')->load(2);

    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() === 200) {
      $raw = $response->getBody()->getContents();
      $data = json_decode($raw);

      foreach ($data->data as $row) {
        $term = NULL;
        $possible_terms = taxonomy_term_load_multiple_by_name($row->label, $vocabulary->id());
        if ($possible_terms) {
          foreach ($possible_terms as $possible_term) {
            if ($possible_term->get('id')->value == $row->id) {
              $term = $possible_term;
              break;
            }
          }
        }

        $display_name = $row->label;
        if (isset($row->operation) && isset($row->operation[0])) {
          $display_name .= ' (' . reset($row->operation)->label . ')';
        }

        if (empty($term)) {
          $item = [
            'name' => $row->label,
            'display_name' => $display_name,
            'vid' => $vocabulary->id(),
            'created' => [],
            'provider_uuid' => [],
            'parent' => [],
            'description' => '',
          ];

          // Set creation time.
          $item['created'][] = [
            'value' => time(),
          ];

          // Set owner.
          $item['provider_uuid'][] = [
            'target_uuid' => $provider->uuid(),
          ];

          // Store HID Id.
          $item['author'][] = [
            'value' => 'Shared',
          ];

          $term = Term::create($item);
        }

        $term->set('name', $row->label);
        $term->set('display_name', $display_name);

        foreach ($fields as $name => $type) {
          $field_name = str_replace('-', '_', $name);
          if ($field_name === 'operations') {
            $name = 'operation';
          }

          if ($term->hasField($field_name)) {
            $value = FALSE;
            if (empty($row->{$name})) {
              continue;
            }
            if (is_array($type) && $type['type'] === 'term_reference') {
              $lookup = $row->{$name};
              if (is_array($lookup)) {
                foreach ($lookup as $lookup_item) {
                  if (empty($lookup_item)) {
                    continue;
                  }
                  if ($lookup_item->label === 'République centrafricaine') {
                    $lookup_item->label = 'Central African Republic';
                  }
                  if ($lookup_item->label === 'Colombie') {
                    $lookup_item->label = 'Colombia';
                  }

                  $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
                    'name' => $lookup_item->label,
                    'vid' => $type['target'],
                  ]);
                  if (!empty($entities)) {
                    $value[] = ['target_uuid' => reset($entities)->uuid()];
                  }
                  else {
                    // @todo Consider creating missing references here.
                    print "\n$field_name reference needs creating:\n";
                    drush_log(serialize($lookup), 'ok');
                    continue;
                  }
                }
              }
              else {
                $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
                  'name' => $lookup->label,
                  'vid' => $type['target'],
                ]);
                if (!empty($entities)) {
                  $value['target_uuid'] = reset($entities)->uuid();
                }
                else {
                  // @todo Consider creating missing references here.
                  print "\n$field_name reference needs creating:\n";
                  drush_log(serialize($lookup), 'ok');
                  continue;
                }
              }
            }
            else {
              $value = $row->{$name};
            }

            // @todo We're updating whether something has changed or not.
            // As there aren't too many, this is okay, but it could be better.
            if (!empty($value)) {
              $term->set($field_name, $value);
            }
          }
        }

        $violations = $term->validate();
        if (count($violations) > 0) {
          print($violations->get(0)->getMessage());
          print($violations->get(0)->getPropertyPath());
        }
        else {
          $term->save();
        }
      }
    }
    else {
      print "\nNo results for $url\n";
    }

    // Check for more data.
    if (isset($data->next) && isset($data->next->href)) {
      print serialize($data->next->href);
      $this->updateClusters($data->next->href);
    }

    $this->state->set('docstore_sync_local_groups_ts', time());
  }

  /**
   * Update operations.
   *
   * @command docstore:update-operations
   * @usage docstore:update-operations
   *   Update operations from HRInfo.
   * @validate-module-enabled docstore
   */
  public function updateOperations($url = '') {
    if (empty($url)) {
      $ts = $this->state->get('docstore_sync_operations_ts', 1641025083);
      $url = 'https://www.humanitarianresponse.info/en/api/v1.0/operations';
      $url .= '?filter[changed][value]=' . $ts . '&filter[changed][operator]=>';
      $url .= '&sort=id';
    }

    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() !== 200) {
      return;
    }

    // Load provider.
    $provider = $this->entityTypeManager->getStorage('user')->load(2);

    // Load vocabulary.
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load('operations');

    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $term = NULL;
      $possible_terms = taxonomy_term_load_multiple_by_name($row->label, $vocabulary->id());
      if ($possible_terms) {
        foreach ($possible_terms as $possible_term) {
          if ($possible_term->get('id')->value == $row->id) {
            $term = $possible_term;
            break;
          }
        }
      }

      if ($term) {
        continue;
      }

      $item = [
        'name' => $row->label,
        'vid' => $vocabulary->id(),
        'created' => [],
        'provider_uuid' => [],
        'parent' => [],
        'description' => '',
      ];

      // Set creation time.
      $item['created'][] = [
        'value' => time(),
      ];

      // Store HID Id.
      $item['author'][] = [
        'value' => 'Shared',
      ];

      $term = Term::create($item);

      $term->set('name', $row->label);
      $term->set('id', $row->id);

      // Set owner.
      $item['provider_uuid'][] = [
        'target_uuid' => $provider->uuid(),
      ];

      // Add country.
      if (isset($row->country) && !empty($row->country)) {
        $value = [];
        $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
          'name' => $row->country->label,
          'vid' => 'countries',
        ]);
        if (!empty($entities)) {
          $value['target_uuid'] = reset($entities)->uuid();
          $term->set('country', $value);
        }
      }

      // Add region/operations.
      if (isset($row->region) && !empty($row->region)) {
        $operation['region_label'] = $row->region->label;
        $value = [];
        $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
          'name' => $row->region->label,
          'vid' => 'operations',
        ]);
        if (!empty($entities)) {
          $value['target_uuid'] = reset($entities)->uuid();
          $term->set('region', $value);
        }
      }

      $violations = $term->validate();
      if (count($violations) > 0) {
        print($violations->get(0)->getMessage());
        print($violations->get(0)->getPropertyPath());
      }
      else {
        $term->save();
      }
    }

    // Check for more data.
    if (isset($data->next) && isset($data->next->href)) {
      print $data->next->href;
      $this->updateOperations($data->next->href);
    }

    $this->state->set('docstore_sync_operations_ts', time());
  }

  /**
   * Update organizations.
   *
   * @command docstore:update-organizations
   * @usage docstore:update-organizations
   *   Update organizations from HRInfo.
   * @validate-module-enabled docstore
   */
  public function updateOrganizations($url = '') {
    if (empty($url)) {
      $ts = $this->state->get('docstore_sync_organizations_ts', 1641025083);
      $url = 'https://www.humanitarianresponse.info/en/api/v1.0/organizations';
      $url .= '?filter[changed][value]=' . $ts . '&filter[changed][operator]=>';
      $url .= '&sort=id';
    }

    $response = $this->httpClient->request('GET', $url);
    if ($response->getStatusCode() !== 200) {
      return;
    }

    // Load provider.
    $provider = $this->entityTypeManager->getStorage('user')->load(2);

    // Load vocabulary.
    $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load('organizations');

    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $term = NULL;
      $possible_terms = taxonomy_term_load_multiple_by_name($row->label, $vocabulary->id());
      if ($possible_terms) {
        foreach ($possible_terms as $possible_term) {
          if ($possible_term->get('id')->value == $row->id) {
            $term = $possible_term;
            break;
          }
        }
      }

      if ($term) {
        continue;
      }

      $item = [
        'name' => $row->label,
        'vid' => $vocabulary->id(),
        'created' => [],
        'provider_uuid' => [],
        'parent' => [],
        'description' => '',
      ];

      // Set creation time.
      $item['created'][] = [
        'value' => time(),
      ];

      // Store HID Id.
      $item['author'][] = [
        'value' => 'Shared',
      ];

      $term = Term::create($item);

      $term->set('name', $row->label);
      $term->set('id', $row->id);

      // Set owner.
      $item['provider_uuid'][] = [
        'target_uuid' => $provider->uuid(),
      ];

      // Add organization type.
      if (isset($row->type->label) && !empty($row->type->label)) {
        $value = [];
        $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
          'name' => $row->type->label,
          'vid' => 'organization_types',
        ]);
        if (!empty($entities)) {
          $value['target_uuid'] = reset($entities)->uuid();
          $term->set('organization_type', $value);
        }
      }

      $violations = $term->validate();
      if (count($violations) > 0) {
        print($violations->get(0)->getMessage());
        print($violations->get(0)->getPropertyPath());
      }
      else {
        $term->save();
      }
    }

    // Check for more data.
    if (isset($data->next) && isset($data->next->href)) {
      print $data->next->href;
      $this->updateOrganizations($data->next->href);
    }

    $this->state->set('docstore_sync_organizations_ts', time());
  }

  /**
   * Create territory terms.
   */
  protected function createTerritoryTerms($data) {
    $properties = [
      'region',
      'sub-region',
      'intermediate-region',
    ];

    $territories = [];
    foreach ($properties as $property) {
      if (empty($data->{$property}->code)) {
        continue;
      }

      $territories[] = [
        'code' => $data->{$property}->code,
        'label' => $data->{$property}->label->default,
      ];
    }
    if (empty($territories)) {
      return FALSE;
    }

    $parent = FALSE;
    foreach ($territories as $territory) {
      // Make sure term name is not too long.
      $short_name = $territory['label'];
      if (mb_strlen($territory['label']) > 250) {
        $short_name = Unicode::truncate($territory['label'], 250, TRUE, TRUE);
      }

      if (!$parent) {
        $existing = taxonomy_term_load_multiple_by_name($short_name, 'territories');
      }
      else {
        $parent_tid = 0;
        $item = $parent->get('tid')->getValue();
        if (!empty($item[0])) {
          $parent_tid = $item[0]['value'];
        }
        $query = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->getQuery()
          ->condition('name', $short_name)
          ->condition('parent', $parent_tid);
        $tids = $query->execute();
        if (!empty($tids)) {
          $existing = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->loadMultiple($tids);
        }
      }

      if (!empty($existing)) {
        $term = reset($existing);
        $parent = $term;
        continue;
      }

      $term_data = [
        'vid' => 'territories',
        'name' => $short_name,
        'description' => $territory['label'],
        'code' => [],
      ];

      $term_data['code'][] = [
        'value' => $territory['code'],
      ];

      if (isset($parent) && isset($parent_tid)) {
        $term_data['parent'] = $parent_tid;
      }
      $term = Term::create($term_data);
      $term->save();

      $parent = $term;
    }

    return $term;
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
    // Create/reset the test users.
    $this->createTestUsers();

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
   * Create test node type.
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
      'name' => 'Base provider',
      'prefix' => '',
    ]);

    // Test provider 1.
    $this->createProvider(3, [
      'name' => 'Silk test',
      'prefix' => 'silk_',
      'api_keys' => 'abcd',
      'api_keys_read_only' => 'xyzzy',
      'dropfolder' => '../drop_folders/silk',
      'shared_secret' => 'verysecret',
    ]);

    // Test provider 2.
    $this->createProvider(4, [
      'name' => 'Another test',
      'prefix' => 'another_',
      'api_keys' => 'dcba',
      'api_keys_read_only' => 'yzzyx',
    ]);

    // Test provider 3.
    // For creating vocabulary field through api without a prefix.
    $this->createProvider(5, [
      'name' => 'Prefix-less test',
      'prefix' => '',
      'api_keys' => 'zzzz',
    ]);

    $this->logger->success('Successfully created/resetted test users.');
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
    $media = $this->saveFileToDisk($file, $content, $provider);
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
    $user = $storage->load($uid) ?? User::create([
      'uid' => $uid,
      'type' => 'provider',
      'status' => 1,
      'pass' => '',
    ]);

    foreach ($data as $field => $value) {
      $user->set($field, $value);
    }

    $user->save();
  }

}
