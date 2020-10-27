# Clear docstore, test vocabulary CRUD
../../vendor/bin/drush eval "_docstore_setup_testing()"
../../vendor/bin/drush cr

./silk -test.v -silk.url http://127.0.0.1:8080/api silk_vocabulary_crud.md || exit 1;

# Clear docstore, general tests
../../vendor/bin/drush eval "_docstore_setup_testing()"
../../vendor/bin/drush cr

# Add files
(echo -n '{"filename":"test.pdf","mime":"application/pdf","data": "'; base64 ./files/test_xyzzy.pdf; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/files

./silk -test.v -silk.url http://127.0.0.1:8080/api silk_create.md || exit 1;
./silk -test.v -silk.url http://127.0.0.1:8080/api silk_exceptions.md || exit 1;
