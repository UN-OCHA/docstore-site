# Document store

## TODO

### Document types

- [x] check endpoint, use white/black list to avoid clashes
- [x] add permission
- [ ] delete when empty
- [x] implement PUT, PATCH

## Naming conventions for fields, vocabularies

- `base_`: basic data needed to make it work
- `shared_`: fields, vocabularies used by all providers
- `hrinfo_`: fields, vocabularies for hrinfo
- `reliefweb_`: fields, vocabularies for reliefweb
- `unocha_`: fields, vocabularies for unocha

## Documents

### Get list

```bash
curl -X GET "http://docstore.local.docksal/api/documents" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

```bash
curl -g "http://docstore.local.docksal/api/documents?filter[f1][group][conjunction]=OR&filter[p1][condition][path]=silk_my_id&filter[p1][condition][operator]=%3D&filter[p1][condition][value]=42&filter[p1][condition][memberOf]=f1" | jq

curl -g "http://docstore.local.docksal/api/documents?filter[f1][group][conjunction]=OR&filter[p1][condition][path]=silk_my_id&filter[p1][condition][operator]=%3D&filter[p1][condition][value]=42&filter[p1][condition][memberOf]=f1&filter[p2][condition][path]=silk_my_id&filter[p2][condition][operator]=%3D&filter[p2][condition][value]=7&filter[p2][condition][memberOf]=f1" | jq

curl -g "http://docstore.local.docksal/api/documents?filter[f1][group][conjunction]=OR&filter[org][condition][memberOf]=f1&filter[org][condition][path]=silk_organizations_label&filter[org][condition][operator]=%3D&filter[org][condition][value]=WFP" | jq

curl -g "http://docstore.local.docksal/api/documents?filter[f1][group][conjunction]=OR&filter[org][condition][memberOf]=f1&filter[org][condition][path]=silk_organizations_label&filter[org][condition][value]=WF*" | jq

curl -g "http://docstore.local.docksal/api/documents?page[limit]=7&page[offset]=2" | jq

# to be tested

curl -g "http://docstore.local.docksal/api/documents?filter[f1][group][conjunction]=OR&filter[org][condition][memberOf]=f1&filter[org][condition][path]=silk_organizations_label&filter[org][condition][operator]=STARTS_WITH&filter[org][condition][value]=U" | jq

curl -g "http://docstore.local.docksal/api/documents?filter[f1][group][conjunction]=OR&filter[p2][condition][memberOf]=f1&filter[org][condition][path]=silk_organizations&filter[org][condition][operator]=%3D&filter[org][condition][value]=caaa5a37-9717-4fbc-a732-c2ff6da4f1fa" | jq

curl -g "http://docstore.local.docksal/api/documents?filter[f1][group][conjunction]=OR&filter[p1][condition][path]=silk_my_id&filter[p1][condition][operator]=%3D&filter[p1][condition][value]=42&filter[p1][condition][memberOf]=f1&filter[p2][condition][path]=silk_my_id&filter[p2][condition][operator]=%3D&filter[p2][condition][value]=7&filter[p2][condition][memberOf]=f1&filter[org][condition][path]=silk_organizations_label&filter[org][condition][operator]=STARTS_WITH&filter[org][condition][value]=U" | jq
```

### Create document

```bash
curl -X POST "http://docstore.local.docksal/api/documents" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"title\":\"My first document\"}"
```

```bash
(echo -n '{"title":"Doc with files","author":"123456789","files":["42fc7dfc-5943-47a3-ab4e-1d3b8fe335c4", "74ea6b33-8add-4433-b49d-a4181bf037c5"]}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/documents | jq
```

```bash
curl -X POST "http://docstore.local.docksal/api/documents" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"title\":\"My first document\",\"author\":\"123\",\"metadata\":[{\"peter_city\":\"16000b57-a81b-4c42-9162-e5ec356d88c2\"}]}" | jq

(echo -n '{"title":"Doc with term and files","author":"123456789","metadata":[{"peter_city":"16000b57-a81b-4c42-9162-e5ec356d88c2"}],"files":["42fc7dfc-5943-47a3-ab4e-1d3b8fe335c4", "74ea6b33-8add-4433-b49d-a4181bf037c5"]}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/documents | jq
```

### Get document

```bash
curl -X GET "http://docstore.local.docksal/api/documents/88d701de-b7d9-44f9-991b-b31674ac1f0d" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

## Vocabularies

### Get list of vocabularies

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

### Create vocabulary

```bash
curl -X POST "http://docstore.local.docksal/api/vocabularies" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"City\"}"
```

#### Add reference to vocabulary

```bash
curl -X POST "http://docstore.local.docksal/api/document/fields" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"City\",\"target\":\"peter_city\"}"
```

```bash
curl -X POST "http://docstore.local.docksal/api/document/fields" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"My voc field\",\"target\":\"test_my_vocabulary\",\"multiple\":0}"
```

### Get a vocabulary

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/test_my_vocabulary" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/f56fb44b-a17c-4b5e-bf79-afc4e195af86" -H  "accept: application/json" -H  "API-KEY: abcd"
```

### Get vocabulary fields

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/test_my_vocabulary/fields" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/f56fb44b-a17c-4b5e-bf79-afc4e195af86/fields" -H  "accept: application/json" -H  "API-KEY: abcd"
```

### Add vocabulary field

```bash
curl -X POST "http://docstore.local.docksal/api/vocabularies/peter_test1/fields" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"ISO3\",\"target\":\"peter_test1\",\"multiple\":0}"
```

### Get vocabulary terms

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/test_my_vocabulary/terms" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

## Terms

### Get terms

```bash
curl -X GET "http://docstore.local.docksal/api/terms" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

### Create term

```bash
(echo -n '{"label":"Antwerp","vocabulary":"peter_city"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/terms | jq
```

## Creating vocabularies, adding fields

```php
$label = 'My field';
$field_type = 'string';
$multiple = FALSE;
$provider_prefix = 'rw_'; // Retrieve from provider.
$new_field_name = docstore_create_document_field_for_provider($label, $field_type, $multiple, $provider_prefix);

$label = 'My vocabulary';
$bundle = docstore_create_vocabulary_for_provider($label, $provider_prefix);

$label = 'My vocabulary field';
$new_field_name = docstore_create_vocabulary_field_for_provider($bundle, $label, $field_type, $multiple, $provider_prefix);

$label = 'Reference to term';
docstore_create_document_reference_field_for_provider($label, $bundle, TRUE);
```

### Using the API

#### Get fields

```bash
curl -X GET "http://docstore.local.docksal/api/document/fields" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

Response

```json
{
  "nid": "integer",
  "uuid": "uuid",
  "vid": "integer",
  "langcode": "language",
  "type": "entity_reference",
  "revision_timestamp": "created",
  "revision_uid": "entity_reference",
  "revision_log": "string_long",
  "status": "boolean",
  "uid": "entity_reference",
  "title": "string",
  "created": "created",
  "changed": "changed",
  "promote": "boolean",
  "sticky": "boolean",
  "default_langcode": "boolean",
  "revision_default": "boolean",
  "revision_translation_affected": "boolean",
  "base_provider": "entity_reference_uuid",
  "base_author_hid": "string",
  "test_my_string_field": "string"
}
```

#### Add field

```bash
curl -X POST "http://docstore.local.docksal/api/document/fields" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"label\":\"My string field\",\"type\":\"string\"}"
```

Response

```json
{
  "message":"Field added",
  "field_name":"my_string_field"
}
```

## Files

### Create file

#### Create entry

```bash
curl -X POST "http://docstore.local.docksal/api/files" -H  "accept: application/json" -H  "API-KEY: abcd" -H  "Content-Type: application/json" -d "{\"filename\":\"my_test_file.txt\"}" | jq
```

#### Create entry and data

```bash
(echo -n '{"filename":"test.pdf","mime":"application/pdf","data": "'; base64 ~/Documents/test_xyzzy.pdf; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://docstore.local.docksal/api/files | jq
```

#### Create data (binary)

```bash
curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" --data-binary "@updated.pdf" http://docstore.local.docksal/api/files/b51bf47c-b9f1-4fb2-addc-66127ee82c39/content | jq
```

## Private files

- ~~Add visibility to file entity API~~
- ~~Store in private file system~~
- ~~/private/provider/ts/hash/path-to-file~~
- ~~Controller to get provider~~
- ~~Hash = key + path + ts~~

- https://www.chapterthree.com/blog/drupal-8-9-media-entities-private-files-and-broken-access-control
- https://www.drupal.org/project/media_revisions_ui

## Ignore config

```yaml
taxonomy.*
field.field.taxonomy_term.*
field.storage.taxonomy_term.*
~field.storage.taxonomy_term.base_provider_uuid
~field.storage.taxonomy_term.created
field.field.node.document.*
~field.field.node.document.base_author_hid
~field.field.node.document.base_files
field.storage.node.*
~field.storage.node.base_author_hid
~field.storage.node.base_files
core.entity_view_display.taxonomy_term.*
core.entity_form_display.taxonomy_term.*
core.entity_form_display.node.document.default
search_api.index.documents
core.entity_view_display.node.document.teaser
core.entity_view_display.node.document.default
```

## Testing

### SILK

```bash
 ./run.sh
```

Test using proxy

```bash
API=http://0.0.0.0:4010 ./run.sh
```

## Hooks

- Incoming drupal, https://github.com/Bounteous-Inc/webhook_entities
- Test endpoint http://webhook.site/

## Clean

```bash
fin drush entity:delete taxonomy_term --bundle=disaster
fin drush entity:delete node --bundle=assessment
```

## Sync

### Jenkins drush jobs

- `scr --verbose modules/custom/docstore/syncs/docstore_countries.php`
- `scr --verbose modules/custom/docstore/syncs/docstore_disaster_types.php`
- `scr --verbose modules/custom/docstore/syncs/docstore_functional_roles.php`
- `scr --verbose modules/custom/docstore/syncs/docstore_global_coordination_groups.php`
- `scr --verbose modules/custom/docstore/syncs/docstore_local_coordination_groups.php`
- `scr --verbose modules/custom/docstore/syncs/docstore_organization_types.php`
- `scr --verbose modules/custom/docstore/syncs/docstore_population_types.php`
- `scr --verbose modules/custom/docstore/syncs/docstore_themes.php`
- `scr --verbose modules/custom/docstore/syncs/docstore_vulnerable_groups.php`

### Local sync jobs

```bash
php ./create_km.php
php ./create_disasters.php
php ./create_assessments.php
```
