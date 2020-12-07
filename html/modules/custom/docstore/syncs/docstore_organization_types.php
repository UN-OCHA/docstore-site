<?php

/**
 * @file
 * Sync disasters from vocabulary.
 */

use Drupal\docstore\ManageFields;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * List of vocabularies.
 */
function docstore_disasters_vocabularies() {
  return [
    'shared_organization_types' => 'Organization types',
  ];
}

/**
 * List of fields.
 */
function docstore_disasters_fields() {
  return [
    'shared_organization_types' => [
      'id' => 'integer',
      'reliefweb_id' => 'integer',
      'reliefweb_label' => 'string',
      'scope' => 'string',
    ],
  ];
}

/**
 * Ensure vocabularies do exist.
 */
function docstore_disasters_ensure_vocabularies() {
  $provider = user_load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('database'));

  foreach (docstore_disasters_vocabularies() as $machine_name => $label) {
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
function docstore_disasters_ensure_vocabulary_fields() {
  $provider = user_load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('database'));

  foreach (docstore_disasters_fields() as $machine_name => $fields) {
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
 * Sync disasters from vocabulary.
 */
function docstore_organization_types_sync() {
  docstore_disasters_ensure_vocabularies();
  docstore_disasters_ensure_vocabulary_fields();

  $http_client = \Drupal::httpClient();
  $url = 'https://vocabulary.unocha.org/json/beta-v1/organization_types.json';

  // Load vocabulary.
  $vocabulary = Vocabulary::load('shared_organization_types');

  // Load provider.
  $provider = user_load(2);

  $response = $http_client->request('GET', $url);
  if ($response->getStatusCode() === 200) {
    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $term = taxonomy_term_load_multiple_by_name($row->label->default, $vocabulary->id());
      if (!$term) {
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
      }
      else {
        $term = reset($term);
      }

      $row->reliefweb_label = $row->label->reliefweb;

      $fields = docstore_disasters_fields()[$vocabulary->id()];

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
docstore_organization_types_sync();
