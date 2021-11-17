<?php

function updateAssessmentLevels() {
  $updated_uuids = [];
  $handle = fopen(__DIR__ . '/level_of_representation.csv', 'r');

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

    print $row_counter . '. Processing document with id=' . $data['nid'] . "\n";

    // Get assessment document using id.
    $entities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'id' => $data['nid'],
      'type' => 'assessment',
    ]);

    if (!empty($entities)) {
      $document = reset($entities);
    }
    else {
      print 'Document not found ' . $data['nid'] . "\n";
      continue;
    }

    if (!$document->get('level_of_representation')->isEmpty()) {
      print "Skip document, already has contacts\n";
    }

    $document->set('level_of_representation', $data['level']);
    $document->save();
    $updated_uuids[] = $document->uuid();
  }

  fclose($handle);

  foreach ($updated_uuids as $updated_uuid) {
    docstore_notify_webhooks('document:assessment:update', $updated_uuid);
  }

}

updateAssessmentLevels();
