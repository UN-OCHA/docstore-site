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
  post(API_URL . 'api/v1/types', [
    'machine_name' => 'assessment',
    'endpoint' => 'assessments',
    'label' => 'Assessment',
    'shared' => true,
    'content_allowed' => true,
    'fields_allowed' => true,
    'author' => 'common',
    'allow_duplicates' => true,
  ]);

  post(API_URL . 'api/v1/types', [
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
    'Units of measurement' => 'ar_units_of_measurement',
    'Assessment status' => 'ar_assessment_status',
  ];

  foreach ($vocabularies as $vocabulary => $machine_name) {
    post(API_URL . 'api/v1/vocabularies', [
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
    post(API_URL . 'api/v1/types/assessment_document/fields', [
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
      'type' => 'string_long',
      'multiple' => FALSE,
    ],
    'methodology' => [
      'label' => 'Methodology',
      'type' => 'string_long',
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
      'target' => 'organizations',
      'multiple' => TRUE,
    ],
    'asst_organizations' => [
      'label' => 'Other organizations',
      'type' => 'term_reference',
      'target' => 'organizations',
      'multiple' => TRUE,
    ],
    'locations' => [
      'label' => 'Locations',
      'type' => 'term_reference',
      'target' => 'locations',
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

    post(API_URL . 'api/v1/types/assessment/fields', $data);
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
      'files' => [],
      'id' => $row->id,
      'other_location' => $row->other_location ?? '',
      'subject' => $row->subject ?? '',
      'methodology' => $row->methodology ?? '',
      'key_findings' => $row->key_findings ?? '',
      'sample_size' => $row->sample_size ?? '',
      'frequency' => $row->frequency ?? '',
      'ar_status_label' => $row->status ?? '',
    ];

    if (isset($row->collection_method)) {
      $data_collection_method = [];
      foreach ($row->collection_method as $collection_method) {
        $data_collection_method[] = $collection_method;
      }
      $assessment['collection_method'] = $data_collection_method;
    }

    if (isset($row->operation)) {
      $data_operation = [];
      foreach ($row->operation as $operation) {
        if (!empty($operation)) {
          $data_operation[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'operations',
            '_field' => 'id',
            '_value' => $operation->id,
          ];
        }

        if (!empty($data_operation)) {
          $assessment['operations'] = $data_operation;
        }
      }
    }

    if (isset($row->date)) {
      $assessment['ar_date'] =[
        'value' => str_replace(' ', 'T', $row->date->value),
        'end_value' => str_replace(' ', 'T', $row->date->value2),
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
          '_field' => 'glide',
          '_value' => $disaster->glide,
        ];
      }

      $assessment['disasters'] = $disaster_data;
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
            '_value' => $bundle->id,
          ];
        }
      }

      $assessment['local_groups'] = $bundle_data;
    }

    // Organizations.
    if (isset($row->organizations) && !empty($row->organizations)) {
      $organization_data = [];
      foreach ($row->organizations as $organization) {
        if (isset($organization->label)) {
          $organization_data[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'organizations',
            '_field' => 'id',
            '_value' => $organization->id,
          ];
        }
      }

      $assessment['organizations'] = $organization_data;
    }

    // Participating organizations.
    if (isset($row->participating_organizations) && !empty($row->participating_organizations)) {
      $organization_data = [];
      foreach ($row->participating_organizations as $organization) {
        if (isset($organization->label)) {
          $organization_data[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'organizations',
            '_field' => 'id',
            '_value' => $organization->id,
          ];
        }
      }

      $assessment['asst_organizations'] = $organization_data;
    }

    // Locations.
    if (isset($row->locations) && !empty($row->locations)) {
      $locations_data = [];
      foreach ($row->locations as $organization) {
        $locations_data[] = $organization->label;
      }

      $assessment['locations_label'] = $locations_data;
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
            '_value' => $population_type->id,
          ];
        }
      }

      $assessment['population_types'] = $population_type_data;
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
            '_value' => $theme->id,
          ];
        }
      }

      $assessment['themes'] = $theme_data;
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
        $assessment['units_of_measurement_label'] = $unit_measurement_data;
      }
    }

    // Report.
    if (isset($row->report) && !empty($row->report)) {
      $report_data = buildAssessmentDocument($row->report);

      // Pass as an array.
      if ($report_data) {
        $assessment['assessment_report'] = [$report_data];
      }
    }

    // Questionnaires.
    if (isset($row->questionnaire) && !empty($row->questionnaire)) {
      $questionnaire_data = buildAssessmentDocument($row->questionnaire);

      // Pass as an array.
      if ($questionnaire_data) {
        $assessment['assessment_questionnaire'] = [$questionnaire_data];
      }
    }

    // Data.
    if (isset($row->data) && !empty($row->data)) {
      $data_data = buildAssessmentDocument($row->data);

      // Pass as an array.
      if ($data_data) {
        $assessment['assessment_data'] = [$data_data];
      }
    }

    $assessment['author'] = 'AR';
    $assessments[] = $assessment;
  }

  $post_data = [
    'author' => 'AR',
    'documents' => $assessments,
  ];

  post(API_URL . 'api/v1/documents/assessments/bulk', $post_data);

  // Check for more data.
  if (isset($data->next->href)) {
    print $data->next->href;
    syncAssesments($data->next->href);
  }
}

function buildAssessmentDocument($data) {
  if (!isset($data->accessibility) || $data->accessibility === 'Not Available') {
    return FALSE;
  }

  if (empty($data->file->filename) && empty($data->file->url) && empty($data->instructions)) {
    return FALSE;
  }

  $output = [
    '_action' => 'create',
    '_reference' => 'node',
    '_target' => 'assessment_document',
    '_data' => [
      'author' => 'AR',
      'title' => $data->file->filename ?? 'Not applicable',
      'files' => [],
      'accessibility' => $data->accessibility ?? 'Publicly Available',
      'instructions' => $data->instructions ?? '',
    ],
  ];

  if (isset($data->file->filename)) {
    $output['_data']['title'] = $data->file->filename;
  }

  if (isset($data->file->url)) {
    $xoutput['_data']['files'][] = [
      'uri' => $data->file->url,
    ];
    $output['_data']['files'][] = [
      'filename' => $data->file->filename,
    ];
  }

  return $output;
}

createNodeType();
createVocabularies();
createAssessmentDocumentFields();
createAssessmentFields();
syncAssesments();
