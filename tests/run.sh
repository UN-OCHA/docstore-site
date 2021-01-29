# Env vars
DRUSH=${DRUSH:-"fin drush"}
SILK=${SILK:-".silk"}
HOST=${HOST:-"http://docstore.local.docksal"}
API=${API:-"$HOST/api/v1"}

# Clear docstore, test vocabulary CRUD
$DRUSH eval "_docstore_setup_testing()"
$DRUSH cr

# Add document type
$DRUSH eval "docstore_create_node_type('document', 'documents')"

$SILK -test.v -silk.url $API silk_webhooks.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_crud.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_bulk.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_anon_cud.md || exit 1;
$SILK -test.v -silk.url $API silk_vocabulary_anon_r.md || exit 1;
$SILK -test.v -silk.url $API silk_document_types_crud.md || exit 1;
$SILK -test.v -silk.url $API silk_document_bulk.md || exit 1;
$SILK -test.v -silk.url $API silk_geofield.md || exit 1;
$SILK -test.v -silk.url $API silk_linkfield.md || exit 1;
$SILK -test.v -silk.url $API silk_child_terms.md || exit 1;
$SILK -test.v -silk.url $API silk_private.md || exit 1;
$SILK -test.v -silk.url $API silk_document_revisions.md || exit 1;
$SILK -test.v -silk.url $API silk_term_revisions.md || exit 1;

# Clear docstore, general tests
$DRUSH eval "_docstore_setup_testing()"
$DRUSH cr

# Add files
(echo -n '{"private":true,"filename":"private.pdf","mime":"application/pdf","data": "'; base64 ./files/private.pdf; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  $API/files > newfile_private.json
export FILEPRIVATE=$(cat newfile_private.json | awk -F '"' '{print $8}')
curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" --data-binary "@./files/private_updated.pdf" $API/files/$FILEPRIVATE/content

(echo -n '{"private":false,"filename":"public.pdf","mime":"application/pdf","data": "'; base64 ./files/public.pdf; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  $API/files > newfile_public.json
export FILEPUBLIC=$(cat newfile_public.json | awk -F '"' '{print $8}')
curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" --data-binary "@./files/public_updated.pdf" $API/files/$FILEPUBLIC/content

(echo -n '{"private":true,"filename":"private.txt","mime":"application/txt","data": "'; base64 ./files/private.txt; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  $API/files > newfile_private_txt.json
export FILEPRIVATETXT=$(cat newfile_private_txt.json | awk -F '"' '{print $8}')
curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" --data-binary "@./files/private_updated.txt" $API/files/$FILEPRIVATETXT/content

(echo -n '{"private":false,"filename":"public.txt","mime":"application/txt","data": "'; base64 ./files/public.txt; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  $API/files > newfile_public_txt.json
export FILEPUBLICTXT=$(cat newfile_public_txt.json | awk -F '"' '{print $8}')
curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" --data-binary "@./files/public_updated.txt" $API/files/$FILEPUBLICTXT/content

## Set shared secret.
(echo -n '{"shared_secret":"verysecret"}') | curl -X PATCH -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  $API/me

curl -X GET -H  "accept: application/json" -H  "API-KEY: abcd" $API/me > me.json
export ME_UUID=$(cat me.json | awk -F '"' '{print $4}')
export ME_SHARED=$(cat me.json | awk -F '"' '{print $16}')
export HASH=$(php -r "print md5('$ME_SHARED$FILEPRIVATETXT$ME_UUID');")

$SILK -test.v -silk.url $HOST silk_files_direct.md || exit 1;
$SILK -test.v -silk.url $API silk_files.md || exit 1;
$SILK -test.v -silk.url $API silk_create.md || exit 1;
$SILK -test.v -silk.url $API silk_exceptions.md || exit 1;

## Add countries vocabulary.
$DRUSH --verbose scr ../html/modules/custom/docstore/syncs/docstore_countries.php

$SILK -test.v -silk.url $API silk_document_crud.md || exit 1;
