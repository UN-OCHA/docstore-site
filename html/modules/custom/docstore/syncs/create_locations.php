<?php

const API_URL = 'http://docstore.local.docksal/';
//const API_URL = 'https://docstore.test/';
//const API_URL = 'https://ocha:dev@dev.docstore-unocha-org.ahconu.org/';

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
    // CURLOPT_SSL_VERIFYPEER => FALSE,
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
    'locations' => "Locations",
  ];

  foreach ($vocabularies as $machine_name => $label) {
    post(API_URL . 'api/v1/vocabularies', [
      'machine_name' => $machine_name,
      'label' => $label,
      'author' => 'HRINFO',
    ]);
  }
}

function createLocationFields() {
  $fields = [
    'id' => [
      'label' => 'Id',
      'type' => 'string',
    ],
    'pcode' => [
      'label' => 'P-code',
      'type' => 'string',
    ],
    'iso3' => [
      'label' => 'P-code',
      'type' => 'string',
    ],
    'admin_level' => [
      'label' => 'Admin level',
      'type' => 'integer',
    ],
    'geolocation' => [
      'label' => 'Geo location',
      'type' => 'geofield',
    ],
  ];

  foreach ($fields as $machine_name => $field) {
    print('Creating ' . $field['label']);
    $data = [
      'label' => $field['label'],
      'machine_name' => $machine_name,
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'HRINFO',
    ];

    if (isset($field['target'])) {
      $data['target'] = $field['target'];
    }

    post(API_URL . 'api/v1/vocabularies/locations/fields', $data);
  }
}

function syncLocations($url = '', $admin_level = 0) {
  if (empty($url)) {
    $ts = 0;

    $api_endpoint = 'https://www.humanitarianresponse.info/en/api/v1.0/locations';
    $url = $api_endpoint . '?filter[admin_level]=' . $admin_level;
    $url .= '&filter[changed][value]=' . $ts . '&filter[changed][operator]=>';
    $url .= '&sort=changed,id';
  }

  $raw = file_get_contents($url);
  $data = json_decode($raw);

  $locations = [];
  foreach ($data->data as $row) {
    $location = [
      'label' => $row->label,
      'id' => $row->id,
      'pcode' => $row->pcode,
      'iso3' => $row->iso3,
      'admin_level' => $row->admin_level,
      'label' => $row->label,
    ];

    if (isset($row->geolocation) && isset($row->geolocation->lat)) {
      $location['geolocation'] = [
        'lat' => $row->geolocation->lat,
        'lon' => $row->geolocation->lon,
        'value' => 'POINT (' . $row->geolocation->lat . ' ' . $row->geolocation->lon . ')',
      ];
    }

    $location['author'] = 'HRINFO';
    $locations[] = $location;
  }

  $post_data = [
    'author' => 'HRINFO',
    'terms' => $locations,
  ];

  post(API_URL . 'api/v1/vocabularies/locations/terms/bulk', $post_data);

  // Check for more data.
  if (isset($data->next) && isset($data->next->href)) {
    print $data->next->href;
    syncLocations($data->next->href, $admin_level);
  }
}

createVocabularies();
createLocationFields();
syncLocations();
