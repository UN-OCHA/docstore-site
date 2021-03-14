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
    'operations' => 'Operations',
  ];

  foreach ($vocabularies as $machine_name => $label) {
    post(API_URL . 'api/v1/vocabularies', [
      'machine_name' => $machine_name,
      'label' => $label,
      'author' => 'HRINFO',
    ]);
  }
}

function createOperationFields() {
  $fields = [
    'id' => [
      'label' => 'Id',
      'type' => 'string',
    ],
    'homepage' => [
      'label' => 'Homepage',
      'type' => 'string',
    ],
    'email' => [
      'label' => 'Email',
      'type' => 'email',
    ],
    'operation_type' => [
      'label' => 'Type',
      'type' => 'string',
    ],
    'operation_status' => [
      'label' => 'Status',
      'type' => 'string',
    ],
    'country' => [
      'label' => 'Country',
      'type' => 'term_reference',
      'target' => 'countries',
      'multiple' => FALSE,
    ],
    'timezone' => [
      'label' => 'Timezone',
      'type' => 'string',
    ],
    'launch_date' => [
      'label' => 'Launch date',
      'type' => 'datetime',
    ],
    'region' => [
      'label' => 'Region',
      'type' => 'term_reference',
      'target' => 'operations',
      'multiple' => FALSE,
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

    post(API_URL . 'api/v1/vocabularies/operations/fields', $data);
  }
}

function syncOperations($url = '') {
  if (empty($url)) {
    $api_endpoint = 'https://www.humanitarianresponse.info/en/api/v1.0/operations';
    $url = $api_endpoint . '?sort=id';
  }

  $raw = file_get_contents($url);
  $data = json_decode($raw);

  $operations = [];
  foreach ($data->data as $row) {
    $operation = [
      'label' => $row->label,
      'id' => $row->id,
      'homepage' => $row->homepage ?? '',
      'email' => $row->email ?? '',
      'operation_type' => $row->type ?? '',
      'operation_status' => $row->status ?? '',
      'timezone' => $row->timezone ?? '',
      'launch_date' => $row->launch_date ? date('Y-m-d\TH:i:s', $row->launch_date) : '',
    ];

    // Add country.
    if (isset($row->country) && !empty($row->country)) {
      $operation['country_label'] = $row->country->label;
    }

    // Add country.
    if (isset($row->region) && !empty($row->region)) {
      $operation['region_label'] = $row->region->label;
    }

    $operation['author'] = 'HRINFO';
    $operations[] = $operation;
  }

  $post_data = [
    'author' => 'HRINFO',
    'terms' => $operations,
  ];

  post(API_URL . 'api/v1/vocabularies/operations/terms/bulk', $post_data);

  // Check for more data.
  if (isset($data->next) && isset($data->next->href)) {
    print $data->next->href;
    syncOperations($data->next->href);
  }
}

createVocabularies();
createOperationFields();
syncOperations();
