<?php

/**
 * @file
 * Sync countries from vocabulary.
 */

use DMore\ChromeDriver\HttpClient;
use Drupal\docstore\ManageFields;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * List of vocabularies.
 */
function docstore_countries_vocabularies() {
  return [
    'shared_countries' => 'Countries',
    'shared_regions' => 'Regions',
  ];
}

/**
 * List of fields.
 */
function docstore_countries_fields() {
  return [
    'shared_countries' => [
      'admin_level' => 'integer',
      'dgacm-list' => 'boolean',
      'fts_api_id' => 'integer',
      'hrinfo_id' => 'integer',
      'id' => 'integer',
      'iso2' => 'string',
      'iso3' => 'string',
      'm49' => 'integer',
      'regex' => 'string',
      'reliefweb_id' => 'integer',
      'unterm-list' => 'boolean',
      'x-alpha-2' => 'string',
      'x-alpha-3' => 'string',
    ],
  ];
}

/**
 * Ensure vocabularies do exist.
 */
function docstore_countries_ensure_vocabularies() {
  $provider = user_load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('database'));

  foreach (docstore_countries_vocabularies() as $machine_name => $label) {
    $vocabulary = Vocabulary::load($machine_name);
    if (!$vocabulary) {
      $vocabulary = $manager->createVocabulary([
        'label' => $label,
        'machine_name' => $machine_name,
        'author' => 'Shared',
        'allow_duplicates' => FALSE,
      ]);
    }
  }
}

/**
 * Ensure vocabulary fields do exist.
 */
function docstore_countries_ensure_vocabulary_fields() {
  $provider = user_load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('database'));

  foreach (docstore_countries_fields() as $machine_name => $fields) {
    $vocabulary = Vocabulary::load($machine_name);
    foreach ($fields as $label => $type) {
      $field_name = $manager->addVocabularyField($vocabulary, [
        'label' => $label,
        'type' => $type,
        'author' => 'Shared',
      ]);
    }
  }
}

function docstore_countries_sync() {
  docstore_countries_ensure_vocabularies();
  docstore_countries_ensure_vocabulary_fields();

  $http_client = \Drupal::httpClient();
  $url = 'https://vocabulary.unocha.org/json/beta-v3/countries.json';

  // Load vocabulary.
  $vocabulary = Vocabulary::load('shared_countries');

  // Load provider.
  $provider = user_load(2);

  $response = $http_client->request('GET', $url);
  if ($response->getStatusCode() === 200) {
    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $item = [
        'name' => $row->label->default,
        'vid' => $vocabulary->id(),
        'created' => [],
        'base_provider_uuid' => [],
        'parent' => [],
        'description' => '',
      ];

      // Set creation time.
      $item['created'][] = [
        'value' => time(),
      ];

      // Set owner.
      $item['base_provider_uuid'][] = [
        'target_uuid' => $provider->uuid(),
      ];

      // Store HID Id.
      $item['base_author_hid'][] = [
        'value' => 'Shared',
      ];

      $term = Term::create($item);

      $fields = docstore_countries_fields()['shared_countries'];
      foreach ($fields as $name => $type) {
        $field_name = str_replace('-', '_', $name);
        if ($term->hasField($field_name) && isset($row->{$name})) {
          // @todo boolean.
          $value = $row->{$name};
          if ($type === 'boolean') {
            if ($value === 'Y') {
              $value = TRUE;
            }
            else {
              $value = FALSE;
            }
          }
          $term->set($field_name, $value);
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
}

// Auto execute.
docstore_countries_sync();
