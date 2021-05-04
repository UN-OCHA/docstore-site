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
  $vocabulary = Vocabulary::load('local_coordination_groups');

  // Load fields this vocabulary already has and skip those that exist.
  $existing_fields = array_keys(\Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'local_coordination_groups'));

  foreach (docstore_local_coordination_groups_fields() as $fields) {
    foreach ($fields as $label => $type) {
      if (in_array($label, $existing_fields)) {
        continue;
      }
      print("\nField $label to be added\n");
      if (is_array($type)) {
        $manager->addVocabularyField($vocabulary, [
          'label' => $label,
          'machine_name' => $label,
          'type' => $type['type'],
          'target' => $type['target'],
          'multiple' => $type['multiple'],
          'author' => 'Shared',
        ]);
      }
      else {
        $manager->addVocabularyField($vocabulary, [
          'label' => $label,
          'machine_name' => $label,
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

  // Get vocabulary fields.
  $fields = docstore_local_coordination_groups_fields()[$vocabulary->id()];

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

      // Needed once to update all terms.
      $check = \Drupal::state()->get('docstore_sync_local_groups_name_update', '');
      if (empty($check)) {
        $term->set('display_name', $display_name);
      }

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
    docstore_local_coordination_groups_sync($data->next->href);
  }
}

// Auto execute.
docstore_local_coordination_groups_sync();
\Drupal::service('docstore.vocabulary_controller')->rebuildAccessibleResourceTypes('taxonomy_vocabulary');
\Drupal::state()->set('docstore_sync_local_groups_name_update', 'processed');
