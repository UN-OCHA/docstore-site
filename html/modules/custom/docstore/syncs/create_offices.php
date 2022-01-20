<?php

// fin drush entity:delete node --bundle=assessment
// fin drush entity:delete node --bundle=assessment_document

const API_URL = 'http://docstore.local.docksal/';

function post($url, $data) {
  $ch = curl_init($url);
print_r([
  '------------------',
  $url,
  '------------------',
]);
  curl_setopt_array($ch, [
    CURLOPT_POST => TRUE,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HTTPHEADER => [
      'API-KEY: hrinfo',
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
    'Offices' => 'offices',
  ];

  foreach ($vocabularies as $vocabulary => $machine_name) {
    post(API_URL . 'api/v1/vocabularies', [
      'label' => $vocabulary,
      'machine_name' => $machine_name,
      'author' => 'HRInfo',
    ]);
  }
}

function createFields() {
  $fields = [
    'id' => [
      'label' => 'Id',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'address' => [
      'label' => 'Address',
      'type' => 'address',
      'multiple' => FALSE,
    ],
    'email' => [
      'label' => 'Email',
      'type' => 'email',
    ],
    'is_coordination_hub' => [
      'label' => 'Is coordination hub?',
      'type' => 'boolean',
      'multiple' => FALSE,
    ],
    'phones' => [
      'label' => 'Phones',
      'type' => 'telephone',
      'multiple' => TRUE,
    ],
    'operations' => [
      'label' => 'Operations',
      'type' => 'term_reference',
      'target' => 'operations',
      'multiple' => TRUE,
    ],
    'organizations' => [
      'label' => 'Organizations',
      'type' => 'term_reference',
      'target' => 'organizations',
      'multiple' => TRUE,
    ],
    'locations' => [
      'label' => 'Locations',
      'type' => 'term_reference',
      'target' => 'locations',
      'multiple' => FALSE,
    ],
  ];

  foreach ($fields as $machine_name => $field) {
    $data = [
      'label' => $field['label'],
      'machine_name' => $machine_name,
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'HRInfo',
    ];

    if (isset($field['target'])) {
      $data['target'] = $field['target'];
    }

    post(API_URL . 'api/v1/vocabularies/offices/fields', $data);
  }
}

function syncAssesments($url = '') {
  if (empty($url)) {
    $url = 'https://www.humanitarianresponse.info/en/api/v1.0/offices';
  }

  $raw = file_get_contents($url);
  $data = json_decode($raw);

  $assessments = [];
  foreach ($data->data as $row) {
    $assessment = [
      'label' => $row->label,
      'id' => $row->id,
      'email' => $row->email ?? '',
      'is_coordination_hub' => $row->coordination_hub ?? '',
    ];

    if (isset($row->address)) {
      $assessment['address'] = [
        'country_code' => $row->address->country ?? '',
        //'administrative_area' => $row->address->thoroughfare ?? '',
        'locality' => $row->address->locality ?? '',
        'dependent_locality' => $row->address->dependent_locality ?? '',
        'postal_code' => $row->address->postal_code ?? '',
        'address_line1' => $row->address->premise ?? '',
        'address_line2' => $row->address->sub_premise ?? '',
      ];
    }

    if (isset($row->phones)) {
      $data_phones = [];
      foreach ($row->phones as $phone) {
        // @todo lookup $phone->countrycode
        $data_phones[] = $phone->number;
      }
      $assessment['phones'] = $data_phones;
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

    // Locations.
    if (isset($row->location) && !empty($row->location)) {
      $location_data = [];
      foreach ($row->location as $location) {
        if (isset($location->label)) {
          $location_data[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'location',
            '_field' => 'id',
            '_value' => $location->id,
          ];
        }
      }

      $assessment['location'] = $location_data;
    }

    $assessment['author'] = 'HRInfo';
    $assessments[] = $assessment;
  }

  $post_data = [
    'author' => 'HRInfo',
    'terms' => $assessments,
  ];

  post(API_URL . 'api/v1/vocabularies/offices/terms/bulk', $post_data);

  // Check for more data.
  if (isset($data->next->href)) {
    print $data->next->href;
    syncAssesments($data->next->href);
  }
}

//createVocabularies();
//createFields();
syncAssesments();
