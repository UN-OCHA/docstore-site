<?php

/**
 * @file
 * Sync countries from vocabulary.
 */

use Drupal\Component\Utility\Unicode;
use Drupal\docstore\ManageFields;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * List of vocabularies.
 */
function docstore_countries_vocabularies() {
  return [
    'countries' => 'Countries',
    'territories' => 'Territories',
  ];
}

/**
 * List of fields.
 */
function docstore_countries_fields() {
  return [
    'countries' => [
      'admin_level' => 'integer',
      'dgacm-list' => 'boolean',
      'fts_api_id' => 'integer',
      'hrinfo_id' => 'integer',
      'id' => 'string',
      'iso2' => 'string',
      'iso3' => 'string',
      'm49' => 'integer',
      'regex' => 'string',
      'reliefweb_id' => 'integer',
      'unterm-list' => 'boolean',
      'x-alpha-2' => 'string',
      'x-alpha-3' => 'string',
      'geolocation' => 'geofield',
      'territory' => [
        'type' => 'entity_reference_uuid',
        'target' => 'territories',
      ],
    ],
    'territories' => [
      'code' => 'string',
    ],
  ];
}

/**
 * Ensure vocabularies do exist.
 */
function docstore_countries_ensure_vocabularies() {
  $provider = User::load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('entity_type.manager'), Drupal::service('database'));

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
  $provider = User::load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('entity_type.manager'), Drupal::service('database'));

  foreach (docstore_countries_fields() as $machine_name => $fields) {
    $vocabulary = Vocabulary::load($machine_name);
    foreach ($fields as $label => $type) {
      if (is_array($type)) {
        $manager->addVocabularyField($vocabulary, [
          'label' => $label,
          'type' => $type['type'],
          'target' => $type['target'],
          'author' => 'Shared',
        ]);
      }
      else {
        $manager->addVocabularyField($vocabulary, [
          'label' => $label,
          'type' => $type,
          'author' => 'Shared',
        ]);
      }
    }
  }
}

/**
 * Create territory terms.
 */
function docstore_countries_territory_term($data) {
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
      $query = \Drupal::service('entity_type.manager')
        ->getStorage('taxonomy_term')
        ->getQuery()
        ->condition('name', $short_name)
        ->condition('parent', $parent_tid);
      $tids = $query->execute();
      $existing = \Drupal::service('entity_type.manager')
        ->getStorage('taxonomy_term')
        ->loadMultiple($tids);
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
 * Sync countries from vocabulary.
 */
function docstore_countries_sync() {
  docstore_countries_ensure_vocabularies();
  docstore_countries_ensure_vocabulary_fields();

  $http_client = \Drupal::httpClient();
  $url = 'https://vocabulary.unocha.org/json/beta-v3/countries.json';

  // Load vocabulary.
  $vocabulary = Vocabulary::load('countries');

  // Load provider.
  $provider = User::load(2);

  $response = $http_client->request('GET', $url);
  if ($response->getStatusCode() === 200) {
    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $term = taxonomy_term_load_multiple_by_name($row->label->default, 'countries');
      if (!$term) {
        $item = [
          'name' => $row->label->default,
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
      else {
        $term = reset($term);
      }

      $fields = docstore_countries_fields()['countries'];
      foreach ($fields as $name => $type) {
        $field_name = str_replace('-', '_', $name);
        if ($term->hasField($field_name)) {
          $value = FALSE;
          if (isset($row->{$name})) {
            $value = $row->{$name};
          }

          if ($type === 'boolean') {
            if ($value === 'Y') {
              $value = TRUE;
            }
            else {
              $value = FALSE;
            }
          }

          if ($type === 'geofield') {
            if (empty($value->lat) || empty($value->lon)) {
              continue;
            }

            $value = [
              'lat' => $value->lat,
              'lon' => $value->lon,
              'value' => 'POINT (' . $value->lat . ' ' . $value->lon . ')',
            ];
          }

          if ($field_name === 'territory') {
            $territory_term = docstore_countries_territory_term($row);
            if (!$territory_term) {
              continue;
            }

            $term->territory = [];
            $term->territory[] = [
              'target_id' => $territory_term->id(),
            ];
            continue;
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
\Drupal::service('docstore.vocabulary_controller')->rebuildAccessibleResourceTypes('taxonomy_vocabulary');
