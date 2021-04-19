# Env vars
DRUSH=${DRUSH:-"fin drush"}
SILK=${SILK:-"./silk"}
HOST=${HOST:-"http://docstore.local.docksal"}
API=${API:-"$HOST/api/v1"}

# Helpers to extract uuids.
get_uuid() {
  sed -nE 's/.*"uuid":"([^"]+)".*/\1/p'
}
get_file_uuid() {
  sed -nE 's/.*"file_uuid":"([^"]+)".*/\1/p'
}
get_media_uuid() {
  sed -nE 's/.*"media_uuid":"([^"]+)".*/\1/p'
}

# Stop the webhook server.
stop_webhook_server() {
  if [ "$DOCSTORE_PHP_WEBHOOK_SERVER_PID" != "" ]; then
    echo "Stopping webhook test server"
    kill "$DOCSTORE_PHP_WEBHOOK_SERVER_PID"
    DOCSTORE_PHP_WEBHOOK_SERVER_PID=
  fi
}

# Ensure the webhook server is stopped on error/exit.
trap stop_webhook_server 0

# Reset docstore for testing.
$DRUSH docstore:test-reset

# Run base tests.
$SILK -test.v -silk.url "$API" silk_vocabulary_crud.md || exit 1;
$SILK -test.v -silk.url "$API" silk_vocabulary_bulk.md || exit 1;
$SILK -test.v -silk.url "$API" silk_vocabulary_bulk_cud.md || exit 1;
$SILK -test.v -silk.url "$API" silk_vocabulary_anon_cud.md || exit 1;
$SILK -test.v -silk.url "$API" silk_vocabulary_anon_r.md || exit 1;
$SILK -test.v -silk.url "$API" silk_document_types_crud.md || exit 1;
$SILK -test.v -silk.url "$API" silk_document_crud.md || exit 1;
$SILK -test.v -silk.url "$API" silk_document_bulk.md || exit 1;
$SILK -test.v -silk.url "$API" silk_document_bulk_cud.md || exit 1;
$SILK -test.v -silk.url "$API" silk_geofield.md || exit 1;
$SILK -test.v -silk.url "$API" silk_linkfield.md || exit 1;
$SILK -test.v -silk.url "$API" silk_child_terms.md || exit 1;
$SILK -test.v -silk.url "$API" silk_private.md || exit 1;
$SILK -test.v -silk.url "$API" silk_document_revisions.md || exit 1;
$SILK -test.v -silk.url "$API" silk_term_revisions.md || exit 1;

# Reset docstore for testing.
$DRUSH docstore:test-reset

# Start the PHP webhook server.
php -S localhost:8765 -t webhooks &
DOCSTORE_PHP_WEBHOOK_SERVER_PID=$!
sleep 2
export WEBHOOK_SERVER_URL=http://localhost:8765

# Test webhooks.
$SILK -test.v -silk.url "$API" silk_webhooks.md || exit 1;

# Stop webhook server
stop_webhook_server

# Reset docstore for testing.
$DRUSH docstore:test-reset

# Test the document files endpoint.
$SILK -test.v -silk.url "$API" silk_document_files.md || exit 1;

# Reset docstore for testing.
$DRUSH docstore:test-reset

# Prepare for file related tests.
FILE_PRIVATE=$(base64 ./files/private.pdf | tr -d '\n')
export FILE_PRIVATE

# Run file tests.
$SILK -test.v -silk.url "$API" silk_files.md || exit 1;

# Run tests that depends on the files.
$SILK -test.v -silk.url "$API" silk_create.md || exit 1;
$SILK -test.v -silk.url "$API" silk_exceptions.md || exit 1;

# Reset docstore for testing.
$DRUSH docstore:test-reset

# Run direct download tests.
$SILK -test.v -silk.url "$HOST" silk_files_direct.md || exit 1;
