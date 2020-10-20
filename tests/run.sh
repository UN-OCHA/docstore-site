#fin drush eval "_docstore_setup_testing()"
#./silk -test.v -silk.url http://docstore.local.docksal/api silk_create.md

fin drush --uri=test eval "_docstore_setup_testing()"
./silk -test.v -silk.url http://test.docstore.local.docksal/api silk_create.md
