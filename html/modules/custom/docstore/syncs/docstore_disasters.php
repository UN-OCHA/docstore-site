<?php

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;


/**
 * Bring in recent disasters, updating/ creating as necessary.
 *
 * Using the RW ids for the id field. Not sure if this needs clarifying.
 *
 * @todo this gives some errors connected to revisionUser uuid, which isn't
 * defined:
 * `[warning] array_flip(): Can only flip STRING and INTEGER values!
 * EntityStorageBase.php:266`
 */
function syncDisasters($date = '', $url = '') {

  // @todo is this right?
  // Load provider.
  $provider = User::load(2);

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
  $raw = file_get_contents($url);
  $data = json_decode($raw);

  foreach ($data->data as $row) {
    $node = NULL;
    $possible_nodes = NULL;
    $possible_nodes = \Drupal::entityTypeManager()
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

      // @todo is this right?
      // Store HID Id.
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
    $status_term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $row->fields->status]);
    if (empty($status_term)) {
      // create it.
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

      // @todo is this right?
      // Store HID Id.
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
        $term_ids = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid', 'disaster_types')
          ->condition('common_disaster_type_code', $type->code)
          ->execute();
        $term_id = reset($term_ids);
        $type_data[] = [
          'target_id' => $term_id,
        ];
      }
      $node->set('disaster_type', $type_data);
    }

    // Primary disaster type.
    if (isset($row->fields->primary_type) && !empty($row->fields->primary_type)) {
      $term_ids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'disaster_types')
        ->condition('common_disaster_type_code', $row->fields->primary_type->code)
        ->execute();
      $term_id = reset($term_ids);
      $type = ['target_id' => $term_id];
      $node->set('primary_disaster_type', $type);
    }

    // Countries.
    if (isset($row->fields->country) && !empty($row->fields->country)) {
      $country_data = [];
      foreach ($row->fields->country as $country) {
        $term_ids = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid', 'countries')
          ->condition('common_iso3', $country->iso3)
          ->execute();
        $term_id = reset($term_ids);
        $country_data[] = [
          'target_id' => $term_id,
        ];
      }
      $node->set('countries', $country_data);
    }

    // Primary country.
    if (isset($row->fields->primary_country) && !empty($row->fields->primary_country)) {
      $term_ids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', 'countries')
        ->condition('common_iso3', $row->fields->primary_country->iso3)
        ->execute();
      $term_id = reset($term_ids);
      $country = ['target_id' => $term_id];
      $node->set('primary_country', $country);
    }

    $node->set('author', 'RW');

    $violations = $node->validate();
    if (count($violations) > 0) {
      print($violations->get(0)->getMessage());
      print($violations->get(0)->getPropertyPath());
    }
    else {
      $node->save();
    }
  }



  // Check for more data.
  if (isset($data->links) && isset($data->links->next->href)) {
    print $data->links->next->href;
    syncDisasters($date, $data->links->next->href);
  }
}

// drush_shift() isn't working here. It would be an improvement.
$days_ago = $_SERVER['argv'][4];
if (!isset($days_ago)) {
  die('The script needs a number of days ago to start its query.');
}
$date = date(DATE_ATOM, mktime(0, 0, 0, date('m'), date('d')-$days_ago, date('Y')));
print ("\n " . urlencode($date) . "\n");
syncDisasters($date);
