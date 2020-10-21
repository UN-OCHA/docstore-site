fin drush eval "_docstore_setup_testing()"
./silk -test.v -silk.url http://docstore.local.docksal/api silk_create.md
./silk -test.v -silk.url http://docstore.local.docksal/api silk_exceptions.md
