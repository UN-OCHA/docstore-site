<?php

const API_URL = 'http://docstore.local.docksal/';

function post($url, $data) {
  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_POST => TRUE,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HTTPHEADER => [
      'API-KEY: abcd',
      'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
  ]);

  // Send the request.
  $response = curl_exec($ch);
  print_r($response);

  // Check for errors.
  if ($response === FALSE) {
    die(curl_error($ch));
  }
}

function createVocabularies() {
  $vocabularies = [
    'disaster_status',
  ];

  foreach ($vocabularies as $vocabulary) {
    post(API_URL . 'api/vocabularies', [
      'label' => $vocabulary,
      'author' => 'RW',
    ]);
  }
}

function createDisasterFields() {
  $fields = [
    'id' => [
      'label' => 'Id',
      'type' => 'integer',
    ],
    'glide' => [
      'label' => 'Glide number',
      'type' => 'string',
    ],
    'disaster_status' => [
      'label' => 'Disaster status',
      'type' => 'term_reference',
      'target' => 'silk_disaster_status',
    ],
    'primary_country' => [
      'label' => 'Primary country',
      'type' => 'term_reference',
      'target' => 'shared_countries',
      'multiple' => FALSE,
    ],
    'countries' => [
      'label' => 'Country',
      'type' => 'term_reference',
      'target' => 'shared_countries',
      'multiple' => TRUE,
    ],
    'profile' => [
      'label' => 'Profile',
      'type' => 'string_long',
    ],
    'description' => [
      'label' => 'Description',
      'type' => 'string_long',
    ],
    'disaster_type' => [
      'label' => 'Disaster type',
      'type' => 'term_reference',
      'target' => 'shared_disaster_types',
      'multiple' => TRUE,
    ],
    'primary_disaster_type' => [
      'label' => 'Primary disaster type',
      'type' => 'term_reference',
      'target' => 'shared_disaster_types',
      'multiple' => FALSE,
    ],
  ];

  foreach ($fields as $field) {
    print('Creating ' . $field['label']);
    $data = [
      'label' => $field['label'],
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'RW',
    ];

    if (isset($field['target'])) {
      $data['target'] = $field['target'];
    }

    post(API_URL . 'api/fields/disasters', $data);
  }
}

function syncDisasters($url = '') {
  if (empty($url)) {
    $url = 'https://api.reliefweb.int/v1/disasters?appname=vocabulary&preset=external&limit=100';
    $url .= '&fields[include][]=country.iso3';
    $url .= '&fields[include][]=primary_country.iso3';
    $url .= '&fields[include][]=profile.overview';
    $url .= '&fields[include][]=description';
    $url .= '&fields[include][]=type.code';
    $url .= '&fields[include][]=primary_type.code';
  }

  $raw = file_get_contents($url);
  $data = json_decode($raw);

  $disasters = [];
  foreach ($data->data as $row) {
    $disaster = [
      'title' => $row->fields->name,
      'metadata' => [],
      'files' => [],
    ];

    // Id.
    $disaster['metadata'][] = ['silk_id' => $row->fields->id];

    // Status.
    $disaster['metadata'][] = ['silk_disaster_status' => $row->fields->status];

    // Glide.
    if (isset($row->fields->glide)) {
      $disaster['metadata'][] = ['silk_glide_number' => $row->fields->glide];
    }

    // Profile.
    if (isset($row->fields->profile->overview)) {
      $disaster['metadata'][] = ['silk_profile' => $row->fields->profile->overview];
    }

    // Description.
    if (isset($row->fields->description)) {
      $disaster['metadata'][] = ['silk_description' => $row->fields->description];
    }

    // Disaster type.
    if (isset($row->fields->type) && !empty($row->fields->type)) {
      $type_data = [];
      foreach ($row->fields->type as $type) {
        $type_data[] = [
          '_action' => 'lookup',
          '_reference' => 'term',
          '_target' => 'shared_disaster_types',
          '_field' => 'disaster_type_code',
          'value' => $type->code,
        ];
      }

      $disaster['metadata'][] = ['silk_disaster_type' => $type_data];
    }

    // Primary disaster type.
    if (isset($row->fields->primary_type) && !empty($row->fields->primary_type)) {
      $type_data = [
        '_action' => 'lookup',
        '_reference' => 'term',
        '_target' => 'shared_disaster_types',
        '_field' => 'disaster_type_code',
        'value' => $row->fields->primary_type->code,
      ];

      $disaster['metadata'][] = ['silk_primary_disaster_type' => [$type_data]];
    }

    // Country.
    if (isset($row->fields->country) && !empty($row->fields->country)) {
      $country_data = [];
      foreach ($row->fields->country as $country) {
        $country_data[] = [
          '_action' => 'lookup',
          '_reference' => 'term',
          '_target' => 'shared_countries',
          '_field' => 'iso3',
          'value' => $country->iso3,
        ];
      }

      $disaster['metadata'][] = ['silk_country' => $country_data];
    }

    // Primary disaster type.
    if (isset($row->fields->primary_country) && !empty($row->fields->primary_country)) {
      $country_data = [
        '_action' => 'lookup',
        '_reference' => 'term',
        '_target' => 'shared_countries',
        '_field' => 'iso3',
        'value' => $row->fields->primary_country->iso3,
      ];

      $disaster['metadata'][] = ['silk_primary_country' => [$country_data]];
    }

    $disaster['author'] = 'RW';
    $disasters[] = $disaster;
  }

  $post_data = [
    'author' => 'RW',
    'documents' => $disasters,
  ];

  post(API_URL . 'api/disasters/bulk', $post_data);

  // Check for more data.
  if (isset($data->links) && isset($data->links->next->href)) {
    print $data->links->next->href;
    syncDisasters($data->links->next->href);
  }
}

createVocabularies();
createDisasterFields();
syncDisasters();