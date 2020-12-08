<?php

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
    post('http://docstore.local.docksal/api/vocabularies', [
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
  ];

  foreach ($fields as $field) {
    $data = [
      'label' => $field['label'],
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'RW',
    ];

    if (isset($field['target'])) {
      $data['target'] = $field['target'];
    }

    post('http://docstore.local.docksal/api/fields/disasters', $data);
  }
}

function syncDisasters($url = '') {
  if (empty($url)) {
    $url = 'https://api.reliefweb.int/v1/disasters?appname=vocabulary&preset=external&limit=100';
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

    $disaster['metadata'][] = ['silk_id' => $row->fields->id];

    if (isset($row->fields->glide)) {
      $disaster['metadata'][] = ['silk_glide_number' => $row->fields->glide];
    }

    $disaster['metadata'][] = ['silk_disaster_status' => $row->fields->status];

    $disaster['author'] = 'RW';
    $disasters[] = $disaster;
  }

  $post_data = [
    'author' => 'RW',
    'documents' => $disasters,
  ];

  post('http://docstore.local.docksal/api/disasters/bulk', $post_data);

  // Check for more data.
  if (isset($data->links) && isset($data->links->next->href)) {
    print $data->links->next->href;
    syncDisasters($data->links->next->href);
  }
}

createVocabularies();
createDisasterFields();
syncDisasters();
