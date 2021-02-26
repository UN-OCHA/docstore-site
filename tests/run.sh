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

# Reset docstore for testing.
$DRUSH docstore:test-reset

# Add document type.
# @todo check if that's used in the tests and whether that should be replaced
# by an API call where relevant.
$DRUSH docstore:test-create-node-type document documents

# Add countries vocabulary.
# @todo it's only used for the sild_document_crud tests and only to
# look up Aruba. Replace that with a command to create a vocabulary and a test
# term instead as it's pretty slow.
$DRUSH --verbose scr ../html/modules/custom/docstore/syncs/docstore_countries.php

# Run base tests.
$SILK -test.v -silk.url $API silk_webhooks.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_crud.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_bulk.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_bulk_cud.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_anon_cud.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_anon_r.md || exit 1;
$SILK -test.v -silk.url $API silk_document_types_crud.md || exit 1;
$SILK -test.v -silk.url $API silk_document_crud.md || exit 1;
$SILK -test.v -silk.url $API silk_document_bulk.md || exit 1;
$SILK -test.v -silk.url $API silk_document_bulk_cud.md || exit 1;
$SILK -test.v -silk.url $API silk_geofield.md || exit 1;
$SILK -test.v -silk.url $API silk_linkfield.md || exit 1;
$SILK -test.v -silk.url $API silk_child_terms.md || exit 1;
$SILK -test.v -silk.url $API silk_private.md || exit 1;
$SILK -test.v -silk.url $API silk_document_revisions.md || exit 1;
$SILK -test.v -silk.url $API silk_term_revisions.md || exit 1;

# Reset docstore for testing.
$DRUSH docstore:test-reset

# Test the document files endpoint.
$SILK -test.v -silk.url $API silk_document_files.md || exit 1;

# Reset docstore for testing.
$DRUSH docstore:test-reset

# Add document type
$DRUSH docstore:test-create-node-type document documents

# Prepare for file related tests.
export FILE_PRIVATE=$(base64 -w 0 ./files/private.pdf)

# Run file tests.
$SILK -test.v -silk.url $API silk_files.md || exit 1;

# Run tests that depends on the files.
$SILK -test.v -silk.url $API silk_create.md || exit 1;
$SILK -test.v -silk.url $API silk_exceptions.md || exit 1;

# Prepare for direct download tests.
export ME_UUID="$(curl -s -H "API-KEY: abcd" $API/me | get_uuid)"
export MEDIA_UUID=$($DRUSH --pipe docstore:test-create-file "$ME_UUID" "direct.txt" "Direct txt" 1)
export MEDIA_HASH=$($DRUSH --pipe docstore:test-create-file-url-hash "$ME_UUID" "$MEDIA_UUID")
export FILE_UUID=$(curl -s -H "API-KEY: abcd" "$API/media/$MEDIA_UUID" | get_file_uuid)
export FILE_HASH=$($DRUSH docstore:test-create-file-url-hash "$ME_UUID" "$FILE_UUID")

# Run direct download tests.
# @todo add test for the media hash after ensuring the /media/ endpoint it
# properly handled by nginx.
$SILK -test.v -silk.url $HOST silk_files_direct.md || exit 1;
