<?php

/**
 * @file
 * Sync themes from vocabulary.
 */

use Drupal\docstore\ManageFields;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * List of vocabularies.
 */
function docstore_themes_vocabularies() {
  return [
    'shared_themes' => 'Themes',
  ];
}

/**
 * List of fields.
 */
function docstore_themes_fields() {
  return [
    'shared_themes' => [
      'id' => 'integer',
    ],
  ];
}

/**
 * Ensure vocabularies do exist.
 */
function docstore_themes_ensure_vocabularies() {
  $provider = user_load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('database'));

  foreach (docstore_themes_vocabularies() as $machine_name => $label) {
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
function docstore_themes_ensure_vocabulary_fields() {
  $provider = user_load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('database'));

  foreach (docstore_themes_fields() as $machine_name => $fields) {
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
 * Sync themes from vocabulary.
 */
function docstore_disaster_types_sync() {
  docstore_themes_ensure_vocabularies();
  docstore_themes_ensure_vocabulary_fields();

  $http_client = \Drupal::httpClient();
  $url = 'https://api.reliefweb.int/v1/references/themes?appname=vocabulary';

  // Load vocabulary.
  $vocabulary = Vocabulary::load('shared_themes');

  // Load provider.
  $provider = user_load(2);

  $response = $http_client->request('GET', $url);
  if ($response->getStatusCode() === 200) {
    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $term = taxonomy_term_load_multiple_by_name($row->fields->name, $vocabulary->id());
      if (!$term) {
        $item = [
          'name' => $row->fields->name,
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
      }
      else {
        $term = reset($term);
      }

      $fields = docstore_themes_fields()[$vocabulary->id()];
      // Add description field.
      $fields['description'] = 'string';

      foreach ($fields as $name => $type) {
        $field_name = str_replace('-', '_', $name);
        if ($term->hasField($field_name)) {
          $value = FALSE;
          if (isset($row->fields->{$name})) {
            $value = $row->fields->{$name};
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
docstore_disaster_types_sync();
