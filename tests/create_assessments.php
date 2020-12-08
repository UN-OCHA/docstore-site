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
    'organizations',
    'locations',
    'disasters',
    'units_of_measurement',
  ];

  foreach ($vocabularies as $vocabulary) {
    post('http://docstore.local.docksal/api/vocabularies', [
      'label' => $vocabulary,
      'author' => 'AR',
    ]);
  }
}

function createAssessmentDocumentFields() {
  $fields = [
    'accessibility' => [
      'label' => 'Accessibility',
      'type' => 'string',
    ],
    'instructions' => [
      'label' => 'Instructions',
      'type' => 'string_long',
    ],
  ];

  foreach ($fields as $field) {
    post('http://docstore.local.docksal/api/fields/assessment-documents', [
      'label' => $field['label'],
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'AR',
    ]);
  }
}

function createAssessmentFields() {
  $fields = [
    'contacts' => [
      'label' => 'Contacts',
      'type' => 'string_long',
      'multiple' => TRUE,
    ],
    'local_groups' => [
      'label' => 'Local groups',
      'type' => 'term_reference',
      'target' => 'shared_local_coordination_groups',
      'multiple' => TRUE,
    ],
    'organizations' => [
      'label' => 'Organizations',
      'type' => 'term_reference',
      'target' => 'silk_organizations',
      'multiple' => TRUE,
    ],
    'asst_organizations' => [
      'label' => 'Other organizations',
      'type' => 'term_reference',
      'target' => 'silk_organizations',
      'multiple' => TRUE,
    ],
    'locations' => [
      'label' => 'Locations',
      'type' => 'term_reference',
      'target' => 'silk_locations',
      'multiple' => TRUE,
    ],
    'population_types' => [
      'label' => 'population_types',
      'type' => 'term_reference',
      'target' => 'shared_population_types',
      'multiple' => TRUE,
    ],
    'themes' => [
      'label' => 'themes',
      'type' => 'term_reference',
      'target' => 'shared_themes',
      'multiple' => TRUE,
    ],
    'units_of_measurement' => [
      'label' => 'units_of_measurement',
      'type' => 'term_reference',
      'target' => 'silk_units_of_measurement',
      'multiple' => TRUE,
    ],
    'disasters' => [
      'label' => 'Disasters',
      'type' => 'term_reference',
      'target' => 'silk_disasters',
      'multiple' => TRUE,
    ],
    'assessment_data' => [
      'label' => 'Assessment data',
      'type' => 'node_reference',
      'target' => 'assessment_document',
      'multiple' => TRUE,
    ],
    'assessment_report' => [
      'label' => 'Assessment report',
      'type' => 'node_reference',
      'target' => 'assessment_document',
      'multiple' => TRUE,
    ],
    'assessment_questionnaire' => [
      'label' => 'Assessment questionnaire',
      'type' => 'node_reference',
      'target' => 'assessment_document',
      'multiple' => TRUE,
    ],
  ];

  foreach ($fields as $field) {
    $data = [
      'label' => $field['label'],
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'AR',
    ];

    if (isset($field['target'])) {
      $data['target'] = $field['target'];
    }

    post('http://docstore.local.docksal/api/fields/assessments', $data);
  }
}

createVocabularies();
createAssessmentDocumentFields();
createAssessmentFields();
