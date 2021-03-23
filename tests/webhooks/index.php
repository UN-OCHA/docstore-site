<?php

/**
 * @file
 * Test webhooks.
 */

/**
 * Store data in the shared memory.
 */
function save_data($data) {
  // Only keep the last 10 request payload.
  $data = array_merge([$data], load_data());
  $data = implode('|', array_slice($data, 0, 10));

  $shm_key = ftok(__FILE__, 't');
  $shm_id = shmop_open($shm_key, 'c', 0644, 10000);

  if (!empty($shm_id)) {
    shmop_write($shm_id, strlen($data) . ':' . $data, 0);
    shmop_close($shm_id);
  }
}

/**
 * Load data from the shared memory.
 */
function load_data() {
  $shm_key = ftok(__FILE__, 't');
  $shm_id = shmop_open($shm_key, 'a', 0644, 10000);
  if (!empty($shm_id)) {
    $data = shmop_read($shm_id, 0, 0);
    shmop_close($shm_id);
  }
  if (!empty($data)) {
    list($length, $data) = explode(':', $data, 2);
    return explode('|', substr($data, 0, (int) $length));
  }
  return [];
}

// Simple request handler: store the received data on POST, load it on GET.
http_response_code(200);
switch ($_SERVER['REQUEST_METHOD']) {
  case 'POST':
    save_data(file_get_contents('php://input'));
    break;

  case 'GET':
    header('Content-Type: application/json');
    print json_encode(array_map('json_decode', load_data()), JSON_PRETTY_PRINT) . PHP_EOL;
    break;
}
