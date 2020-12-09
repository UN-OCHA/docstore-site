<?php

$handle = fopen('reg_km.csv', 'r');

// First line is header.
$mapping = [
  'title' => 'title',
  'context' => 'silk_context_label',
  'country' => 'silk_countries_label',
  'document type' => 'silk_document_type_label',
  'global cluster' => 'silk_global_cluster_label',
  'hpc document repository' => 'silk_hpc_document_repository_label',
  'life cycle steps' => 'silk_life_cycle_steps_label',
  'original publication date' => 'silk_original_publication_date',
  'population types' => 'silk_population_types_label',
  'description' => 'silk_description',
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
      if ($header_lowercase[$i] === 'silk_description') {
        $document_params['metadata'][] = [$header_lowercase[$i] => $row[$i]];
      }
      else {
        $row_values = explode(',', $row[$i]);
        $row_values = array_map('trim', $row_values);

        if ($header_lowercase[$i] === 'silk_original_publication_date') {
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

//print(json_encode($data));
//exit();


$ch = curl_init('http://docstore.local.docksal/api/knowledge-managements/bulk');

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

// Check for errors.
if ($response === FALSE) {
  die(curl_error($ch));
}

print $response;
