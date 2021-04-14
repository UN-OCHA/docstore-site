<?php

const API_URL = 'http://docstore.local.docksal/';

function patch($url, $data) {
  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PATCH',
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
}

function get($url) {
  $ch = curl_init($url);

  curl_setopt_array($ch, [
    CURLOPT_POST => FALSE,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json'
    ],
  ]);

  // Send the request.
  $response = curl_exec($ch);

  // Check for errors.
  if ($response === FALSE) {
    die(curl_error($ch));
  }

  $data = json_decode($response, TRUE);
  if (!isset($data['_count'])) {
    print_r($data);
    die();
  }

  return $data;
}

function updateContacts() {
  $handle = fopen('./assessments_contacts.csv', 'r');

  // First line is header.
  $header = fgetcsv($handle, 0, ',', '"');
  $header_lowercase = array_map('strtolower', $header);

  foreach ($header_lowercase as $index => $field_name) {
    $header_lowercase[$index] = trim($field_name);
  }

  $row_counter = 0;
  while ($row = fgetcsv($handle, 0, ',', '"')) {
    $row_counter++;

    $data = [];
    for ($i = 0; $i < count($row); $i++) {
      $data[$header_lowercase[$i]] = trim($row[$i]);
    }

    print $row_counter . '. Processing document with id=' . $data['id'] . "\n";

    // Get assessment document using id.
    $document = get(API_URL . 'api/v1/documents/assessments?filter[id]=' . $data['id']);
    if ($document['_count'] == '0') {
      print 'Skipping ' . $data['id'] . "\n";
    }

    $document = $document['results'][0];
    print 'Found matching document with uuid=' . $document['uuid'] . "\n";

    if (isset($document['contacts']) && !empty($document['contacts'])) {
      print "Skip document, already has contacts\n";
    }

    $contact = '';
    if (!empty($data['name'])) {
      $contact .= $data['name'] . "\r\n";
    }
    if (!empty($data['title'])) {
      $contact .= $data['title'] . "\r\n";
    }
    if (!empty($data['org'])) {
      $contact .= $data['org'] . "\r\n";
    }
    if (!empty($data['email'])) {
      $contact .= $data['email'] . "\r\n";
    }
    if (!empty($data['phone'])) {
      $contact .= $data['phone'] . "\r\n";
    }

    patch(API_URL . 'api/v1/documents/assessments/' . $document['uuid'], [
      'contacts' => [
        $contact,
      ]
    ]);
  }

  fclose($handle);
}

updateContacts();
