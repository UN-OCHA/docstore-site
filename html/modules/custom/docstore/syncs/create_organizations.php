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
    'organizations' => 'Organizations',
    'organization_types' => 'Organization types',
  ];

  foreach ($vocabularies as $machine_name => $label) {
    post(API_URL . 'api/v1/vocabularies', [
      'machine_name' => $machine_name,
      'label' => $label,
      'author' => 'HRINFO',
    ]);
  }
}

function createOrganizationsFields() {
  $fields = [
    'id' => [
      'label' => 'Id',
      'type' => 'string',
    ],
    'acronym' => [
      'label' => 'Acronym',
      'type' => 'string',
    ],
    'homepage' => [
      'label' => 'Homepage',
      'type' => 'string',
    ],
    'fts_id' => [
      'label' => 'FTS Id',
      'type' => 'integer',
    ],
    'organization_type' => [
      'label' => 'Organization type',
      'type' => 'term_reference',
      'target' => 'organization_types',
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

    post(API_URL . 'api/v1/vocabularies/organizations/fields', $data);
  }
}

function syncorganizations($url = '') {
  if (empty($url)) {
    $api_endpoint = 'https://www.humanitarianresponse.info/en/api/v1.0/organizations';
    $url = $api_endpoint . '?sort=id';
  }

  $raw = file_get_contents($url);
  $data = json_decode($raw);

  $organizations = [];
  foreach ($data->data as $row) {
    $organization = [
      'label' => $row->label,
      'id' => $row->id,
      'homepage' => $row->homepage ?? '',
      'acronym' => $row->acronym ?? '',
      'fts_id' => $row->fts_id ?? '',
    ];

    // Add type.
    if (isset($row->type) && !empty($row->type)) {
      $organization['organization_type_label'] = $row->type->label;
    }

    $organization['author'] = 'HRINFO';
    $organizations[] = $organization;
  }

  $post_data = [
    'author' => 'HRINFO',
    'terms' => $organizations,
  ];

  post(API_URL . 'api/v1/vocabularies/organizations/terms/bulk', $post_data);

  // Check for more data.
  if (isset($data->next) && isset($data->next->href)) {
    print $data->next->href;
    syncorganizations($data->next->href);
  }
}

createVocabularies();
createOrganizationsFields();
syncorganizations();
