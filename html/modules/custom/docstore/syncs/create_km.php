<?php

const API_URL = 'http://docstore.local.docksal/';

// fin drush entity:delete node --bundle=knowledge_management

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

function createNodeType() {
  post(API_URL . 'api/v1/types', [
    'machine_name' => 'knowledge_management',
    'endpoint' => 'knowledge-managements',
    'label' => 'Knowledge management',
    'shared' => true,
    'content_allowed' => true,
    'fields_allowed' => true,
    'author' => 'common',
    'allow_duplicates' => true,
  ]);
}

function createVocabularies() {
  $vocabularies = [
    'Context' => 'ar_context',
    'Document type' => 'ar_document_type',
    'HPC document repository' => 'ar_hpc_document_repository',
    'Life cycle steps' => 'ar_life_cycle_steps',
  ];

  foreach ($vocabularies as $vocabulary => $machine_name) {
    post(API_URL . 'api/v1/vocabularies', [
      'label' => $vocabulary,
      'machine_name' => $machine_name,
      'author' => 'AR',
    ]);
  }
}

function createKMFields() {
  $fields = [
    'ar_context' => [
      'label' => 'Context',
      'type' => 'term_reference',
      'target' => 'ar_context',
      'multiple' => TRUE,
    ],
    'countries' => [
      'label' => 'Countries',
      'type' => 'term_reference',
      'target' => 'countries',
      'multiple' => TRUE,
    ],
    'ar_document_type' => [
      'label' => 'Document type',
      'type' => 'term_reference',
      'target' => 'ar_document_type',
      'multiple' => TRUE,
    ],
    'global_cluster' => [
      'label' => 'Global cluster',
      'type' => 'term_reference',
      'target' => 'global_coordination_groups',
      'multiple' => TRUE,
    ],
    'ar_hpc_document_repository' => [
      'label' => 'HPC document repository',
      'type' => 'term_reference',
      'target' => 'ar_hpc_document_repository',
      'multiple' => TRUE,
    ],
    'ar_life_cycle_steps' => [
      'label' => 'Life cycle steps',
      'type' => 'term_reference',
      'target' => 'ar_life_cycle_steps',
      'multiple' => TRUE,
    ],
    'population_types' => [
      'label' => 'Population Types',
      'type' => 'term_reference',
      'target' => 'population_types',
      'multiple' => TRUE,
    ],
    'description' => [
      'label' => 'Description',
      'type' => 'string_long',
    ],
    'original_publication_date' => [
      'label' => 'Original publication date',
      'type' => 'timestamp',
    ],
  ];

  foreach ($fields as $machine_name => $field) {
    print('Creating ' . $field['label']);
    $data = [
      'machine_name' => $machine_name,
      'label' => $field['label'],
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'AR',
    ];

    if (isset($field['target'])) {
      $data['target'] = $field['target'];
    }

    post(API_URL . 'api/v1/fields/knowledge-managements', $data);
  }
}

function syncKM() {
  $handle = fopen('./reg_km.csv', 'r');

  // First line is header.
  $mapping = [
    'title' => 'title',
    'context' => 'ar_context_label',
    'country' => 'countries_label',
    'document type' => 'ar_document_type_label',
    'global cluster' => 'global_cluster_label',
    'hpc document repository' => 'ar_hpc_document_repository_label',
    'life cycle steps' => 'ar_life_cycle_steps_label',
    'original publication date' => 'original_publication_date',
    'population types' => 'population_types_label',
    'description' => 'description',
    'document' => 'document',
  ];

  $header = fgetcsv($handle, 0, ',', '"');
  $header_lowercase = array_map('strtolower', $header);

  foreach ($header_lowercase as $index => $field_name) {
    if (isset($mapping[$field_name])) {
      $header_lowercase[$index] = $mapping[$field_name];
    }
    else {
      // Remove unknown headers.
      unset($header_lowercase[$index]);
    }
  }

  $documents = [];
  while ($row = fgetcsv($handle, 0, ',', '"')) {
    $document_params = [
      'metadata' => [],
      'files' => [],
    ];

    // Convert line to params.
    for ($i = 0; $i < count($row); $i++) {
      // Skip empty fields.
      if (empty(trim($row[$i]))) {
        continue;
      }

      if (!isset($header_lowercase[$i])) {
        continue;
      }

      if ($header_lowercase[$i] === 'title') {
        $document_params[$header_lowercase[$i]] = $row[$i];
      }
      elseif ($header_lowercase[$i] === 'document') {
        $document_params['files'][] = ['uri' => $row[$i]];
      }
      else {
        if ($header_lowercase[$i] === 'description') {
          $document_params['metadata'][] = [$header_lowercase[$i] => $row[$i]];
        }
        else {
          $row_values = explode(',', $row[$i]);
          $row_values = array_map('trim', $row_values);

          if ($header_lowercase[$i] === 'original_publication_date') {
            continue;
          }
          $document_params['metadata'][] = [$header_lowercase[$i] => $row_values];
        }
      }
    }

    // Add common fields.
    $document_params['author'] = 'test';
    $documents[] = $document_params;
  }

  fclose($handle);

  $data = [
    'author' => 'test',
    'documents' => $documents,
  ];

  post(API_URL . 'api/v1/knowledge-managements/bulk', $data);
}

createNodeType();
createVocabularies();
createKMFields();
syncKM();
