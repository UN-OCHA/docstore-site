# Document store

Swagger documentation can be found at https://un-ocha.github.io/doc-store-api/

Code can be found at https://github.com/UN-OCHA/docstore-site

## Remarks

When creating content you have to specify an `author`, this is a basic text field and the document store only keeps track of it, it's up to the client application to add validation.

## Provider ([Docs](https://un-ocha.github.io/doc-store-api/#/Provider))

A provider is a Drupal user with access to the API using API keys.

There are regular API keys and read-only keys available.

To get/update information you can use the `api/me` endpoint.

```bash
curl -X GET "https://docstore.local.docksal/api/me" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

## Document types ([Docs](https://un-ocha.github.io/doc-store-api/#/DocumentType/post-types))

The document store supports multiple document types ("Content types"), these can be created using the API using the `api/types` endpoint.

| field | default | required | info |
| ----- | ------- | -------- | ---- |
| machine_name | | Yes | internal name |
| endpoint | | Yes | the api endpoint to use |
| label | | Yes | visible name |
| shared | true | No | Other users can see these documents |
| content_allowed | true | No | Other providers can create new documents |
| fields_allowed | true | No | Other providers can add their fields |
| author | | Yes | The person who created this |
| allow_duplicates | true | No | Allow duplicate titles |

## Document fields

To add new fields to a document type, you can use the `api/field/{type}` endpoint.

| field | default | required | info |
| -----  | -------- | -------- | ---- |
| label | | Yes | visible name |
| author | | Yes | The person who created this |
| type | | Yes | The type of the field |
| target | | No | The target when the field is a reference field |
| multiple | false | No | Multi value field |
| required | false | No | Required field |

### Supported field types

| type | info |
| ---- | ---- |
| boolean | true or false |
| string | varchar(255) |
| node_reference | reference to another document |
| term_reference | reference to a term |
| email | email address|
| timestamp | datetime |
| integer | integer |
| string_long | long string (blob) |
| geofield | lat/lon coordinates |

## Documents

For all read operation there's and endpoint `api/any` which will query all defined document types. If you want to query 1 specific document type or if you want to create, update or delete a document, you'll have to use to specific endpoint.

| field | default | required | info |
| -----  | -------- | -------- | ---- |
| title | | Yes | visible name |
| author | | Yes | The person who created this |
| published | true | No | Is the document published |
| private | false | No | Is the document private |
| files | [] | No | Array of URI or UUID |
| metadata | [] | No | Array of metadata items

`files` is an array with mixed values. If the value is a string, it's assumed that it's the `uuid` of an existing media item in the document store. If the value is an object containing a property `uri`, that value is use to retrieve the remote file.

`metadata` contains values for fields, the default format is `"field_name": "value"` but other formats are supported as well.

### Reference terms using their label

For all fields referencing terms, the API allows you to use the `_label` suffix to specify the label instead of the uuid.

### Lookup a reference using a custom field

If you specify an object, instead of a plain value, you can lookup reference data using any property you want. This allows you for instance to find a country term using the ISO3 code or allows you to find a disaster document using the GLIDE-number.

Example

```json
  {
    "_action": "lookup",
    "_reference": "term",
    "_target": "shared_local_coordination_group",
    "_field": "id",
    "value": "value"
  }
```

## Vocabularies and terms

## Files

## Examples and code snippets

### Tests

Can be found at [./tests](./tests) and are executed using silk.

The [run.sh](./tests/run.sh) script can be used for local testing and is used by Travis.

### PHP scripts

Can be found at [./html/modules/custom/docstore/syncs](./html/modules/custom/docstore/syncs)

The files with a `create_` prefix are stand alone PHP files, which uses the API to create content.

The files with a `docstore_` prefix are drupal scripts which needs to be executed on the server using `drush scr`

