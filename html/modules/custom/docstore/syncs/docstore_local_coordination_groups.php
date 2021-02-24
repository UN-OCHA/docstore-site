<?php

/**
 * @file
 * Sync local_coordination_groups from vocabulary.
 */

use Drupal\docstore\ManageFields;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * List of vocabularies.
 */
function docstore_local_coordination_groups_vocabularies() {
  return [
    'local_coordination_groups' => 'Local coordination groups',
  ];
}

/**
 * List of fields.
 */
function docstore_local_coordination_groups_fields() {
  return [
    'local_coordination_groups' => [
      'id' => 'string',
      'email' => 'string',
      'website' => 'string',
      'type' => 'string',
      'global_cluster' => [
        'type' => 'term_reference',
        'target' => 'global_coordination_groups',
        'multiple' => FALSE,
      ],
      'lead_agencies' => [
        'type' => 'term_reference',
        'target' => 'hrinfo_organizations',
        'multiple' => TRUE,
      ],
      'partners' => [
        'type' => 'term_reference',
        'target' => 'hrinfo_organizations',
        'multiple' => TRUE,
      ],
      'operation' => [
        'type' => 'term_reference',
        'target' => 'hrinfo_operations',
        'multiple' => TRUE,
      ],
      'ngo_participation' => 'boolean',
      'government_participation' => 'boolean',
      'inter_cluster' => 'boolean',
    ],
  ];
}

/**
 * Ensure vocabularies do exist.
 */
function docstore_local_coordination_groups_ensure_vocabularies() {
  $provider = User::load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('entity_type.manager'), Drupal::service('database'));

  foreach (docstore_local_coordination_groups_vocabularies() as $machine_name => $label) {
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
function docstore_local_coordination_groups_ensure_vocabulary_fields() {
  $provider = User::load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('entity_type.manager'), Drupal::service('database'));

  foreach (docstore_local_coordination_groups_fields() as $machine_name => $fields) {
    $vocabulary = Vocabulary::load($machine_name);
    foreach ($fields as $label => $type) {
      if (is_array($type)) {
        $manager->addVocabularyField($vocabulary, [
          'label' => $label,
          'type' => $type['type'],
          'target' => $type['target'],
          'multiple' => $type['multiple'],
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
 * Sync local_coordination_groups from vocabulary.
 */
function docstore_local_coordination_groups_sync($url = '') {
  if (empty($url)) {
    docstore_local_coordination_groups_ensure_vocabularies();
    docstore_local_coordination_groups_ensure_vocabulary_fields();
    $url = 'https://www.humanitarianresponse.info/en/api/v1.0/bundles';
  }

  $http_client = \Drupal::httpClient();

  // Load vocabulary.
  $vocabulary = Vocabulary::load('local_coordination_groups');

  // Load provider.
  $provider = User::load(2);

  $response = $http_client->request('GET', $url);
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
      if (isset($term)) {
        // @todo Consider updating - do we want this script to handle it?
        print "\nTerm already exists, skipping.\n";
        continue;
      }
      $item = [
        'name' => $row->label,
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

      $fields = docstore_local_coordination_groups_fields()[$vocabulary->id()];
      foreach ($fields as $name => $type) {
        $field_name = str_replace('-', '_', $name);
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
                $uuid = '';
                $entities = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
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
              $entities = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
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
    print "\nNext up:\n" . $data->next->href;
    docstore_local_coordination_groups_sync($data->next->href);
  }
}

// Auto execute.
docstore_local_coordination_groups_sync();
