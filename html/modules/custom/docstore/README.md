# Document store

## Naming conventions for fields, vocabularies

- `base_`: basic data needed to make it work
- `shared_`: fields, vocabularies used by all providers
- `hrinfo_`: fields, vocabularies for hrinfo
- `reliefweb_`: fields, vocabularies for reliefweb
- `unocha_`: fields, vocabularies for unocha

## Vocabularies

### Get list of vocabularies

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies" -H  "accept: application/json" -H  "API-KEY: abcd"
```

### Get a vocabulary

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/base_provider" -H  "accept: application/json" -H  "API-KEY: abcd"
```

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/f56fb44b-a17c-4b5e-bf79-afc4e195af86" -H  "accept: application/json" -H  "API-KEY: abcd"
```

### Get vocabulary fields

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/base_provider/fields" -H  "accept: application/json" -H  "API-KEY: abcd"
```

```bash
curl -X GET "http://docstore.local.docksal/api/vocabularies/f56fb44b-a17c-4b5e-bf79-afc4e195af86/fields" -H  "accept: application/json" -H  "API-KEY: abcd"
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
