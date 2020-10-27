# Clear docstore, test vocabulary CRUD
fin drush eval "_docstore_setup_testing()"
fin drush cr

./silk -test.v -silk.url http://docstore.local.docksal/api silk_vocabulary_crud.md || exit 1;

# Clear docstore, general tests
fin drush eval "_docstore_setup_testing()"
fin drush cr

# Add files
(echo -n '{"filename":"test.pdf","mime":"application/pdf","data": "'; base64 ./files/test_xyzzy.pdf; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/files

./silk -test.v -silk.url http://docstore.local.docksal/api silk_create.md || exit 1;
./silk -test.v -silk.url http://docstore.local.docksal/api silk_exceptions.md || exit 1;
