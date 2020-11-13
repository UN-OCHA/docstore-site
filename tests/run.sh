DRUSH=${DRUSH:-"fin drush"}
API=${API:-"http://docstore.local.docksal/api"}

# Clear docstore, test vocabulary CRUD
$DRUSH eval "_docstore_setup_testing()"
$DRUSH cr

./silk -test.v -silk.url $API silk_vocabulary_crud.md || exit 1;
./silk -test.v -silk.url $API silk_vocabulary_anon_cud.md || exit 1;
./silk -test.v -silk.url $API silk_vocabulary_anon_r.md || exit 1;

# Clear docstore, general tests
$DRUSH eval "_docstore_setup_testing()"
$DRUSH cr

# Add files
(echo -n '{"filename":"test.pdf","mime":"application/pdf","data": "'; base64 ./files/test_xyzzy.pdf; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  $API/files

./silk -test.v -silk.url $API silk_create.md || exit 1;
./silk -test.v -silk.url $API silk_exceptions.md || exit 1;
