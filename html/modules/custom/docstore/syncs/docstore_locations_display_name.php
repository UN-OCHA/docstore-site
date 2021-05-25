<?php

/**
 * @file
 * Add display names to locations.
 */

use Drupal\docstore\ManageFields;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * Locations display name.
 *
 * @param int $admin_level
 *   Admin level
 */
function docstore_location_display_name($level) {
  $query = \Drupal::entityQuery('taxonomy_term');
  $query->condition('vid', 'locations');
  $query->condition('admin_level', $level);
  $tids = $query->execute();

  $count = count($tids);
  print "\nLevel $level count is $count:\n";

  $batches = array_chunk($tids, 100);
  $batch_count = count($batches);
  print "\nNumber of batches = " . $batch_count . "\n";

  $batch_counter = 0;
  foreach ($batches as $batch) {
    for ($i = 0; $i < count($batch); $i++) {
      $term = Term::load($batch[$i]);
      if ($level == 0) {
        $term->set('display_name', $term->get('name')->getString());
      }
      else {
        // Load parent term.
        $parents = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadParents($term->id());
        if (!is_array($parents)) {
          continue;
        }
        $parent = reset($parents);
        if (is_bool($parent)) {
          continue;
        }
        $term->set('display_name', $parent->get('display_name')->getString() . " > " . $term->get('name')->getString());
      }
      $term->save();
    }
    $batch_counter++;
    print "\nLevel $level - Batch $batch_counter of $batch_count done.\n";
  }
}

/**
 * Ensure display_name vocabulary field exists.
 */
function docstore_locations_ensure_vocabulary_field() {
  $provider = User::load(2);
  $manager = new ManageFields($provider, '', Drupal::service('entity_field.manager'), Drupal::service('entity_type.manager'), Drupal::service('database'));
  $vocabulary = Vocabulary::load('locations');

  // Load fields this vocabulary already has and skip those that exist.
  $existing_fields = array_keys(\Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', 'locations'));

  if (in_array('display_name', $existing_fields)) {
    print "\nField 'display_name' already exists for locations.\n";
    return;
  }
  print("\nField 'display_name' to be added.\n");
  $manager->addVocabularyField($vocabulary, [
    'label' => 'Display name',
    'machine_name' => 'display_name',
    'type' => 'string',
    'author' => 'Shared',
  ]);
}

docstore_locations_ensure_vocabulary_field();
foreach ([0,1,2,3] as $level) {
  print "\nLevel $level starting:\n";
  docstore_location_display_name($level);
  print "\nLevel $level done:\n";
}
