<?php

/**
 * @file
 * Sync locations from HRinfo.
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * Sync locations from vocabulary.
 */
function docstore_locations_sync($admin_level, $url = '', $page_number = 0) {
  if (empty($url)) {
    $url = 'https://www.humanitarianresponse.info/en/api/v1.0/locations?filter[admin_level]=' . $admin_level;
    if ($page_number > 0) {
      $url .= '&page[number]=' . $page_number;
    }
  }

  $http_client = \Drupal::httpClient();

  // Load vocabulary.
  $vocabulary = Vocabulary::load('locations');

  // Get vocabulary fields.
  $fields = \Drupal::service('entity_field.manager')
    ->getFieldDefinitions('taxonomy_term', 'locations');

  // @todo is this right?
  // Load provider.
  $provider = User::load(2);

  $response = $http_client->request('GET', $url);
  if ($response->getStatusCode() === 200) {
    $raw = $response->getBody()->getContents();
    $data = json_decode($raw);

    foreach ($data->data as $row) {
      $term = NULL;
      $parent = NULL;
      $possible_terms = NULL;
      $possible_parents = NULL;

      $possible_terms = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'locations')
        ->condition('id', $row->id)
        ->execute();
      $term = Term::load(reset($possible_terms));

      $display_name = $row->label;
      if ($admin_level > 0) {
        if (!empty($row->parent) && !empty($row->parent[0]) && isset($row->parent[0]->id)) {
          $possible_parents = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid', 'locations')
            ->condition('id', $row->parent[0]->id)
            ->execute();
          $parent = Term::load(reset($possible_parents));
          if (!empty($parent->display_name->value)) {
            $parent_display_name = $parent->display_name->value;
            $display_name = $parent_display_name . ' > ' . $display_name;
          }
        }
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

        // @todo is this right?
        // Store HID Id.
        $item['author'][] = [
          'value' => 'Shared',
        ];

        $term = Term::create($item);
      }

      foreach (array_keys($fields) as $name) {

        //$field_name = str_replace('-', '_', $name);

        // Handle special cases.
        if ($field_name == 'id') {
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
    print $data->next->href . "\n";
    docstore_locations_sync($admin_level, $data->next->href);
  }
}

// Execute.
// drush_shift() would help here.
// This is to be run with --verbose flag `drush scr --verbose
// modules/custom/docstore/syncs/docstore_locations_refresh.php 0` where 0 is
// admin level. Sometimes HRinfo api times out, continue using the last page
// number as an extra argument.
$level = $_SERVER['argv'][4];
if (!isset($level)) {
  die('The script needs an admin level to run on.');
}
print "\nLevel $level starting:\n";
$page_number = 0;
if (isset($_SERVER['argv'][5])) {
  $page_number = $_SERVER['argv'][5];
}
print "\nStarting from page number: $page_number\n";
docstore_locations_sync($level, '', $page_number);
print "\nLevel $level done:\n";
