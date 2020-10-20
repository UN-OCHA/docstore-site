curl -X POST "http://docstore.local.docksal/api/vocabularies" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"City\"}"

curl -X POST "http://docstore.local.docksal/api/vocabularies" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"Organization\"}"

curl -X GET "http://docstore.local.docksal/api/vocabularies" -H  "accept: application/json" -H  "API-KEY: abcd" | jq

curl -X POST "http://docstore.local.docksal/api/document/fields" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"City\",\"target\":\"peter_city\"}"

curl -X POST "http://docstore.local.docksal/api/document/fields" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"Organizations\",\"target\":\"peter_organization\",\"multiple\":1}"

(echo -n '{"label":"Antwerp","vocabulary":"peter_city"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/terms | jq

(echo -n '{"label":"Brussels","vocabulary":"peter_city"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/terms | jq

(echo -n '{"label":"Borgerhout","vocabulary":"peter_city"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/terms | jq

curl -X GET "http://docstore.local.docksal/api/vocabularies/peter_city/terms" -H  "accept: application/json" -H  "API-KEY: abcd" | jq

(echo -n '{"label":"CERF","vocabulary":"peter_organization"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/terms | jq

(echo -n '{"label":"WFP","vocabulary":"peter_organization"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/terms | jq

(echo -n '{"label":"UNOCHA","vocabulary":"peter_organization"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/terms | jq

curl -X GET "http://docstore.local.docksal/api/vocabularies/peter_organization/terms" -H  "accept: application/json" -H  "API-KEY: abcd" | jq

(echo -n '{"title":"Doc with term, no files","author":"hid_123456789","metadata":[{"peter_city":"2a6ef841-eafa-41e4-9933-afe33671a7d2"}, {"peter_organizations":["95ac1ef7-c637-448c-9b3d-336ac85bffe8","41e1ef47-e5bb-4f89-b01b-fc0f34092073"]}]}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/documents | jq

curl -X GET "http://docstore.local.docksal/api/documents" -H  "accept: application/json" -H  "API-KEY: abcd" | jq

# Export

fin drush dcer taxonomy_term 1 --folder=../tests/content
fin drush dcer taxonomy_term 2 --folder=../tests/content
fin drush dcer taxonomy_term 3 --folder=../tests/content
fin drush dcer taxonomy_term 4 --folder=../tests/content
fin drush dcer taxonomy_term 5 --folder=../tests/content
fin drush dcer taxonomy_term 6 --folder=../tests/content

fin drush dcer user 22 --folder=../tests/content

fin drush dcer node 18 --folder=../tests/content


curl -X POST "http://docstore.local.docksal/api/vocabularies" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"Test\"}"
