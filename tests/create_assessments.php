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
    'assessment_status',
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
    'id' => [
      'label' => 'Id',
      'type' => 'integer',
      'multiple' => TRUE,
    ],
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
      'type' => 'node_reference',
      'target' => 'disaster',
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

function syncAssesments($url = '') {
  if (empty($url)) {
    $url = 'https://www.humanitarianresponse.info/api/v1.0/assessments';
  }

  $raw = file_get_contents($url);
  $data = json_decode($raw);

  $assessments = [];
  foreach ($data->data as $row) {
    $assessment = [
      'title' => $row->label,
      'metadata' => [],
      'files' => [],
    ];

    $assessment['metadata'][] = ['silk_id' => $row->id];

    if (isset($row->disasters) && !empty($row->disasters)) {
      $disaster_data = [];
      foreach ($row->disasters as $disaster) {
        $disaster_data[] = [
          '_action' => 'lookup',
          '_reference' => 'node',
          '_target' => 'disaster',
          '_field' => 'silk_glide_number',
          'value' => $disaster->glide,
        ];
      }

      $assessment['metadata'][] = ['silk_disasters' => $disaster_data];
    }

    $assessment['author'] = 'AR';
    $assessments[] = $assessment;
  }

  $post_data = [
    'author' => 'AR',
    'documents' => $assessments,
  ];

  post('http://docstore.local.docksal/api/assessments/bulk', $post_data);

  // Check for more data.
  if (isset($data->links) && isset($data->links->next->href)) {
    print $data->links->next->href;
//    syncAssesments($data->links->next->href);
  }
}

createVocabularies();
createAssessmentDocumentFields();
createAssessmentFields();
syncAssesments();
