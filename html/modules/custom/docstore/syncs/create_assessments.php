<?php

// fin drush entity:delete node --bundle=assessment
// fin drush entity:delete node --bundle=assessment_document

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

function createNodeType() {
  post(API_URL . 'api/types', [
    'machine_name' => 'assessment',
    'endpoint' => 'assessments',
    'label' => 'Assessment',
    'shared' => true,
    'content_allowed' => true,
    'fields_allowed' => true,
    'author' => 'common',
    'allow_duplicates' => true,
  ]);

  post(API_URL . 'api/types', [
    'machine_name' => 'assessment_document',
    'endpoint' => 'assessment-documents',
    'label' => 'Assessment document',
    'shared' => true,
    'content_allowed' => true,
    'fields_allowed' => true,
    'author' => 'common',
    'allow_duplicates' => true,
  ]);
}

function createVocabularies() {
  $vocabularies = [
    'Organizations' => 'ar_organizations',
    'Locations' => 'ar_locations',
    'Units of measurement' => 'ar_units_of_measurement',
    'Assessment status' => 'ar_assessment_status',
  ];

  foreach ($vocabularies as $vocabulary => $machine_name) {
    post(API_URL . 'api/vocabularies', [
      'label' => $vocabulary,
      'machine_name' => $machine_name,
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

  foreach ($fields as $machine_name => $field) {
    post(API_URL . 'api/fields/assessment-documents', [
      'label' => $field['label'],
      'machine_name' => $machine_name,
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
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'ar_date' => [
      'label' => 'Date',
      'type' => 'daterange',
    ],
    'other_location' => [
      'label' => 'Other location',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'subject' => [
      'label' => 'Subject',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'methodology' => [
      'label' => 'Methodology',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'key_findings' => [
      'label' => 'Key findings',
      'type' => 'string_long',
      'multiple' => FALSE,
    ],
    'collection_method' => [
      'label' => 'Collection method',
      'type' => 'string',
      'multiple' => TRUE,
    ],
    'operation' => [
      'label' => 'Operations',
      'type' => 'term_reference',
      'target' => 'countries',
      'multiple' => TRUE,
    ],
    'sample_size' => [
      'label' => 'Sample size',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'frequency' => [
      'label' => 'Frequency',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'ar_status' => [
      'label' => 'Status',
      'type' => 'term_reference',
      'target' => 'ar_assessment_status',
      'multiple' => FALSE,
    ],
    'contacts' => [
      'label' => 'Contacts',
      'type' => 'string_long',
      'multiple' => TRUE,
    ],
    'local_groups' => [
      'label' => 'Local groups',
      'type' => 'term_reference',
      'target' => 'local_coordination_groups',
      'multiple' => TRUE,
    ],
    'organizations' => [
      'label' => 'Organizations',
      'type' => 'term_reference',
      'target' => 'ar_organizations',
      'multiple' => TRUE,
    ],
    'asst_organizations' => [
      'label' => 'Other organizations',
      'type' => 'term_reference',
      'target' => 'ar_organizations',
      'multiple' => TRUE,
    ],
    'locations' => [
      'label' => 'Locations',
      'type' => 'term_reference',
      'target' => 'ar_locations',
      'multiple' => TRUE,
    ],
    'population_types' => [
      'label' => 'Population types',
      'type' => 'term_reference',
      'target' => 'population_types',
      'multiple' => TRUE,
    ],
    'themes' => [
      'label' => 'Themes',
      'type' => 'term_reference',
      'target' => 'themes',
      'multiple' => TRUE,
    ],
    'units_of_measurement' => [
      'label' => 'Units of measurement',
      'type' => 'term_reference',
      'target' => 'ar_units_of_measurement',
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

  foreach ($fields as $machine_name => $field) {
    $data = [
      'label' => $field['label'],
      'machine_name' => $machine_name,
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'AR',
    ];

    if (isset($field['target'])) {
      $data['target'] = $field['target'];
    }

    post(API_URL . 'api/fields/assessments', $data);
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

    $assessment['metadata'] = [
      ['id' => $row->id],
      ['other_location' => $row->other_location ?? ''],
      ['subject' => $row->subject ?? ''],
      ['methodology' => $row->methodology ?? ''],
      ['key_findings' => $row->key_findings ?? ''],
      ['sample_size' => $row->sample_size ?? ''],
      ['frequency' => $row->frequency ?? ''],
      ['ar_status_label' => $row->status ?? ''],
    ];

    if (isset($row->collection_method)) {
      $data_collection_method = [];
      foreach ($row->collection_method as $collection_method) {
        $data_collection_method[] = $collection_method;
      }
      $assessment['metadata'][] = ['collection_method' => $data_collection_method];
    }

    if (isset($row->operation)) {
      $data_operation = [];
      foreach ($row->operation as $operation) {
        $data_operation[] = $operation;
        $assessment['metadata'][] = ['operation_label' => $data_operation];
      }
    }

    if (isset($row->date)) {
      $assessment['metadata'][] = [
        'ar_date' => [
          '_action' => 'daterange',
          'value' => str_replace(' ', 'T', $row->date->value),
          'end_value' => str_replace(' ', 'T', $row->date->value2),
        ],
      ];
    }

    // Disasters.
    if (isset($row->disasters) && !empty($row->disasters)) {
      $disaster_data = [];
      foreach ($row->disasters as $disaster) {
        $disaster_data[] = [
          '_action' => 'lookup',
          '_reference' => 'node',
          '_target' => 'disaster',
          '_field' => 'glide_number',
          'value' => $disaster->glide,
        ];
      }

      $assessment['metadata'][] = ['disasters' => $disaster_data];
    }

    // Local coordination groups aka bundles.
    if (isset($row->bundles) && !empty($row->bundles)) {
      $bundle_data = [];
      foreach ($row->bundles as $bundle) {
        if (isset($bundle->id)) {
          $bundle_data[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'local_coordination_groups',
            '_field' => 'id',
            'value' => $bundle->id,
          ];
        }
      }

      $assessment['metadata'][] = ['local_groups' => $bundle_data];
    }

    // Organizations.
    if (isset($row->organizations) && !empty($row->organizations)) {
      $organization_data = [];
      foreach ($row->organizations as $organization) {
        $organization_data[] = $organization->label;
      }

      $assessment['metadata'][] = ['organizations_label' => $organization_data];
    }

    // Participating organizations.
    if (isset($row->participating_organizations) && !empty($row->participating_organizations)) {
      $organization_data = [];
      foreach ($row->participating_organizations as $organization) {
        $organization_data[] = $organization->label;
      }

      $assessment['metadata'][] = ['asst_organizations_label' => $organization_data];
    }

    // Locations.
    if (isset($row->locations) && !empty($row->locations)) {
      $locations_data = [];
      foreach ($row->locations as $organization) {
        $locations_data[] = $organization->label;
      }

      $assessment['metadata'][] = ['locations_label' => $locations_data];
    }

    // Population types.
    if (isset($row->population_types) && !empty($row->population_types)) {
      $population_type_data = [];
      foreach ($row->population_types as $population_type) {
        if (isset($population_type->id)) {
          $population_type_data[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'population_types',
            '_field' => 'id',
            'value' => $population_type->id,
          ];
        }
      }

      $assessment['metadata'][] = ['population_types' => $population_type_data];
    }

    // Themes.
    if (isset($row->themes) && !empty($row->themes)) {
      $theme_data = [];
      foreach ($row->themes as $theme) {
        if (isset($theme->id)) {
          $theme_data[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'themes',
            '_field' => 'id',
            'value' => $theme->id,
          ];
        }
      }

      $assessment['metadata'][] = ['themes' => $theme_data];
    }

    // units_of_measurement.
    if (isset($row->unit_measurement) && !empty($row->unit_measurement)) {
      $unit_measurement_data = [];
      foreach ($row->unit_measurement as $unit) {
        if (!empty($unit)) {
          $unit_measurement_data[] = $unit;
        }
      }

      if (!empty($unit_measurement_data)) {
        $assessment['metadata'][] = ['units_of_measurement_label' => $unit_measurement_data];
      }
    }

    // Report.
    if (isset($row->report) && !empty($row->report)) {
      $report_data = [
        '_action' => 'create',
        '_reference' => 'node',
        '_target' => 'assessment_document',
        '_data' => [
          'author' => 'AR',
          'title' => $row->report->file->filename ?? 'Not applicable',
          'files' => [],
          'metadata' => [
            ['accessibility' => $row->report->accessibility ?? 'Publicly Available'],
            ['instructions' => $row->report->instructions ?? ''],
          ],
        ],
      ];

      if (isset($row->report->file->filename)) {
        $report_data['_data']['title'] = $row->report->file->filename;
      }

      if (isset($row->report->file->url)) {
        $report_data['_data']['files'][] = [
          'uri' => $row->report->file->url,
        ];
      }

      // Pass as an array.
      $assessment['metadata'][] = ['assessment_report' => [$report_data]];
    }

    // Questionnaires.
    if (isset($row->questionnaire) && !empty($row->questionnaire)) {
      $questionnaire_data = [
        '_action' => 'create',
        '_reference' => 'node',
        '_target' => 'assessment_document',
        '_data' => [
          'author' => 'AR',
          'title' => $row->questionnaire->file->filename ?? 'Not applicable',
          'files' => [],
          'metadata' => [
            ['accessibility' => $row->questionnaire->accessibility ?? 'Publicly Available'],
            ['instructions' => $row->questionnaire->instructions ?? ''],
          ],
        ],
      ];

      if (isset($row->questionnaire->file->filename)) {
        $questionnaire_data['_data']['title'] = $row->questionnaire->file->filename;
      }

      if (isset($row->questionnaire->file->url)) {
        $questionnaire_data['_data']['files'][] = [
          'uri' => $row->questionnaire->file->url,
        ];
      }

      // Pass as an array.
      $assessment['metadata'][] = ['assessment_questionnaire' => [$questionnaire_data]];
    }

    // Data.
    if (isset($row->data) && !empty($row->data)) {
      $data_data = [
        '_action' => 'create',
        '_reference' => 'node',
        '_target' => 'assessment_document',
        '_data' => [
          'author' => 'AR',
          'title' => $row->data->file->filename ?? 'Not applicable',
          'files' => [],
          'metadata' => [
            ['accessibility' => $row->data->accessibility ?? 'Publicly Available'],
            ['instructions' => $row->data->instructions ?? ''],
          ],
        ],
      ];

      if (isset($row->data->file->filename)) {
        $data_data['_data']['title'] = $row->data->file->filename;
      }

      if (isset($row->data->file->url)) {
        $data_data['_data']['files'][] = [
          'uri' => $row->data->file->url,
        ];
      }

      // Pass as an array.
      $assessment['metadata'][] = ['assessment_data' => [$data_data]];
    }

    $assessment['author'] = 'AR';
    $assessments[] = $assessment;
  }

  $post_data = [
    'author' => 'AR',
    'documents' => $assessments,
  ];

  post(API_URL . 'api/assessments/bulk', $post_data);

  // Check for more data.
  if (isset($data->links) && isset($data->links->next->href)) {
    print $data->links->next->href;
    syncAssesments($data->links->next->href);
  }
}

createNodeType();
createVocabularies();
createAssessmentDocumentFields();
createAssessmentFields();
syncAssesments();
