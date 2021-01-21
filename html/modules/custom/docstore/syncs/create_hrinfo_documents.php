<?php

const API_URL = 'https://ocha:dev@dev.docstore-unocha-org.ahconu.org/';

function post($url, $data) {
  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_POST => TRUE,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HTTPHEADER => [
      'API-KEY:hrinfo-456',
      'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
  ]);

  // Send the request.
  $response = curl_exec($ch);
  print "Post response:\n";
  print_r($response);

  // Check for errors.
  if ($response === FALSE || curl_errno($ch)) {
    print('Curl HTTP code:' . curl_getinfo($ch, CURLINFO_HTTP_CODE));
    print('Curl url:' . $url);
  }
}

function createNodeType() {
  // Document node type already created, nothing to do here.
}

function createVocabularies() {
  $vocabularies = [
    'Bundles' => 'hrinfo_bundles',
    'Coordination hubs' => 'hrinfo_coordination_hubs',
    'Document type' => 'hrinfo_document_type',
    'Operations' => 'hrinfo_operations',
    'Organizations' => 'hrinfo_organizations',
    'Sectors' => 'hrinfo_sectors',
    'Themes' => 'hrinfo_themes',
    'Webspaces' => 'hrinfo_webspaces',
  ];

  foreach ($vocabularies as $vocabulary => $machine_name) {
    post(API_URL . 'api/vocabularies', [
      'label' => $vocabulary,
      'machine_name' => $machine_name,
      'author' => 'hrinfo',
    ]);
  }
}

function createHrinfoFields() {
  // Files already exists.
  // Not handling: created, changed
  $fields = [
    'hrinfo_id' => [
      'label' => 'Id',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'hrinfo_bundles' => [
      'label' => 'Bundles',
      'type' => 'term_reference',
      'target' => 'hrinfo_bundles',
      'multiple' => TRUE,
    ],
    'hrinfo_coordination_hubs' => [
      'label' => 'Coordination hubs',
      'type' => 'term_reference',
      'target' => 'hrinfo_coordination_hubs',
      'multiple' => TRUE,
    ],
    'hrinfo_countries' => [
      'label' => 'Countries',
      'type' => 'term_reference',
      'target' => 'countries',
      'multiple' => TRUE,
    ],
    'hrinfo_document_type' => [
      'label' => 'Document type',
      'type' => 'term_reference',
      'target' => 'hrinfo_document_type',
      'multiple' => FALSE,
      'required' => TRUE,
    ],
    'hrinfo_operations' => [
      'label' => 'Operations',
      'type' => 'term_reference',
      'target' => 'hrinfo_operations',
      'multiple' => TRUE,
      'required' => TRUE,
    ],
    'hrinfo_organizations' => [
      'label' => 'Organizations',
      'type' => 'term_reference',
      'target' => 'hrinfo_organizations',
      'multiple' => TRUE,
      'required' => TRUE,
    ],
    'hrinfo_sectors' => [
      'label' => 'Sectors',
      'type' => 'term_reference',
      'target' => 'hrinfo_sectors',
      'multiple' => TRUE,
    ],
    'hrinfo_themes' => [
      'label' => 'Themes',
      'type' => 'term_reference',
      'target' => 'themes',
      'multiple' => TRUE,
    ],
    'hrinfo_webspaces' => [
      'label' => 'Webspaces',
      'type' => 'term_reference',
      'target' => 'hrinfo_webspaces',
      'multiple' => TRUE,
    ],
    'hrinfo_body' => [
      'label' => 'Body',
      'type' => 'string_long',
      'multiple' => FALSE,
    ],
    'hrinfo_disasters' => [
      'label' => 'Disasters',
      'type' => 'node_reference',
      'target' => 'disaster',
      'multiple' => TRUE,
    ],
    'hrinfo_exclude_reliefweb' => [
      'label' => 'Exclude from ReliefWeb',
      'type' => 'boolean',
      'multiple' => FALSE,
    ],
    'hrinfo_language' => [
      'label' => 'Language',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'hrinfo_publication_date' => [
      'label' => 'Publication date',
      'type' => 'timestamp',
      'multiple' => FALSE,
    ],
    'hrinfo_published' => [
      'label' => 'Published',
      'type' => 'boolean',
      'multiple' => FALSE,
    ],
    'hrinfo_title' => [
      'label' => 'HRInfo Title',
      'type' => 'string',
      'multiple' => FALSE,
    ],
    'hrinfo_related_content' => [
      'label' => 'Related content',
      'type' => 'link',
      'multiple' => TRUE,
    ],
  ];

  foreach ($fields as $machine_name => $field) {
    $data = [
      'label' => $field['label'],
      'machine_name' => $machine_name,
      'type' => $field['type'],
      'multiple' => $field['multiple'] ?? FALSE,
      'author' => 'hrinfo',
    ];

    if (isset($field['target'])) {
      $data['target'] = $field['target'];
    }

    post(API_URL . 'api/fields/documents', $data);
  }
}

function syncDocuments($url = '', $counter = 1) {
  if (empty($url)) {
    $url = 'https://www.humanitarianresponse.info/api/v1.0/documents';
  }

  $raw = file_get_contents($url);
  if ($raw === FALSE) {
    print "Couldn't get contents from " . $url . "\n";
    print "Trying again in 10 seconds...\n";
    sleep(10);
    syncDocuments($url, $counter);
  }
  $data = json_decode($raw);

  if ($data === NULL) {
    print "Couldn't get valid contents from " . $url . "\n";
    print "Trying again in 10 seconds...\n";
    sleep(10);
    syncDocuments($url, $counter);
  }

  $documents = [];
  foreach ($data->data as $row) {
    $document = [
      'title' => $row->label,
      'metadata' => [],
      'files' => [],
    ];

    $document['metadata'] = [
      ['hrinfo_body' => $row->body ?? ''],
      ['hrinfo_id' => $row->id],
      ['hrinfo_exclude_reliefweb' => (bool) $row->exclude_from_reliefweb ?? FALSE],
      ['hrinfo_language' => $row->language ?? ''],
      ['hrinfo_published' => $row->published ?? ''],
      ['hrinfo_title' => $row->label],
    ];

    if (isset($row->document_type->label)) {
      $document['metadata'][] = ['hrinfo_document_type_label' => $row->document_type->label];
    }

    if (isset($row->publication_date)) {
      $document['metadata'][] = ['hrinfo_publication_date' => strtotime($row->publication_date)];
    }

    // Related content.
    if (isset($row->related_content)) {
      $data_related_content = [];
      foreach ($row->related_content as $related_content) {
        if (!empty($related_content->label)) {
          $data_related_content[] = [
            'uri' => $related_content->uri,
            'title' => $related_content->label,
          ];
        }

        if (!empty($data_related_content)) {
          $document['metadata'][] = [
            'hrinfo_related_content' => $data_related_content,
          ];
        }
      }
    }

    // Global clusters.
    if (isset($row->global_clusters)) {
      $data_global_clusters = [];
      foreach ($row->global_clusters as $global_cluster) {
        if (!empty($global_cluster->label)) {
          $data_global_clusters[] = $global_cluster->label;
        }

        if (!empty($data_global_clusters)) {
          $document['metadata'][] = [
            'hrinfo_sectors_label' => $data_global_clusters,
          ];
        }
      }
    }

    // Space.
    if (isset($row->space)) {
      $data_spaces = [];
      foreach ($row->space as $space) {
        if (!empty($space->label)) {
          $data_spaces[] = $space->label;
        }

        if (!empty($data_spaces)) {
          $document['metadata'][] = ['hrinfo_webspaces_label' => $data_spaces];
        }
      }
    }

    // Offices.
    if (isset($row->offices)) {
      $data_office = [];
      foreach ($row->offices as $office) {
        if (!empty($office->label)) {
          $data_office[] = $office->label;
        }

        if (!empty($data_office)) {
          $document['metadata'][] = [
            'hrinfo_coordination_hubs_label' => $data_office
          ];
        }
      }
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
          'value' => $disaster->glide,
        ];
      }

      $document['metadata'][] = ['hrinfo_disasters' => $disaster_data];
    }

    // Bundles.
    if (isset($row->bundles) && !empty($row->bundles)) {
      $bundle_data = [];
      foreach ($row->bundles as $bundle) {
        if (isset($bundle->id)) {
          $bundle_data[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'hrinfo_bundles',
            '_field' => 'id',
            'value' => $bundle->id,
          ];
        }
      }

      $document['metadata'][] = ['hrinfo_bundles' => $bundle_data];
    }

    // Operations.
    if (isset($row->operation) && !empty($row->operation)) {
      $operation_data = [];
      foreach ($row->operation as $operation) {
        if (isset($operation->label)) {
          $operation_data[] = $operation->label;
        }
      }

      $document['metadata'][] = ['hrinfo_operations_label' => $operation_data];
    }

    // Organizations.
    if (isset($row->organizations) && !empty($row->organizations)) {
      $organization_data = [];
      foreach ($row->organizations as $organization) {
        if (isset($organization->label)) {
          $organization_data[] = $organization->label;
        }
      }

      $document['metadata'][] = [
        'hrinfo_organizations_label' => $organization_data,
      ];
    }

    // Files.
    if (isset($row->files) && !empty($row->files)) {
      foreach ($row->files as $file) {
        if (isset($file->file) && isset($file->file->uri)) {
           $document['files'][] = ['uri' => $file->file->uri];
        }
      }
    }

    // Locations.
    if (isset($row->locations) && !empty($row->locations)) {
      $locations_data = [];
      foreach ($row->locations as $location) {
        if (isset($location->iso3)) {
          $location_data[] = [
            '_action' => 'lookup',
            '_reference' => 'term',
            '_target' => 'hrinfo_countries',
            '_field' => 'iso3',
            'value' => $location->iso3,
          ];
        }
      }

      $document['metadata'][] = ['hrinfo_countries' => $locations_data];
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

      $document['metadata'][] = ['hrinfo_themes' => $theme_data];
    }

    $document['author'] = 'hrinfo';
    $documents[] = $document;
  }

  if (!empty($documents)) {
    $post_data = [
      'author' => 'hrinfo',
      'documents' => $documents,
    ];
  }

  post(API_URL . 'api/documents/bulk', $post_data);
  // Give it some time to process.
  sleep(60);

  // Check for more data but stop before we overload with files.
  if ($counter > 50) {
    print "Stopping at 50 pages";
  }
  elseif (isset($data->next->href)) {
    $counter++;
    print $data->next->href;
    syncDocuments($data->next->href, $counter);
  }
}

createNodeType();
createVocabularies();
createHrinfoFields();
syncDocuments();
