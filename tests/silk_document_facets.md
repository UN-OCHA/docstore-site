# Create documents

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_doc_facets",
  "endpoint": "test-document-facets",
  "label": "Test document facets",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.machine_name: /.+/ // Endpoint {doc_type_machine_name}
* Data.endpoint: /.+/ // Endpoint {doc_type_endpoint}

## POST /types/{doc_type_machine_name}/fields

Add id field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My Id",
  "author": "hid_123456789",
  "type": "integer"
}
```

===

Example output.

```json
{
  "message": "Field created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Field created"
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_id}

## POST /vocabularies

Add test_vocab_facets.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_vocab_facets",
  "label": "Test Vocab Facets",
  "author": "hid_123456789"
}
```

===

Example output.

```json
{
  "message": "Vocabulary created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {test_vocab_facets}

## POST /vocabularies/{test_vocab_facets}/fields

Add display name field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Display Name",
  "machine_name": "display_name",
  "author": "hid_123456789",
  "type": "string"
}
```

===

Example output.

```json
{
  "message": "Field created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Field created"
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {display_name_field}

## POST /vocabularies/{test_vocab_facets}/terms

Add test term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test term 1",
  "author": "hid_123456789",
  "{display_name_field}": "Display name for test term 1"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {test_term_uuid1}

## POST /vocabularies/{test_vocab_facets}/terms

Add test term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test term 2",
  "author": "hid_123456789",
  "{display_name_field}": "Display name for test term 2"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {test_term_uuid2}

## POST /types/{doc_type_machine_name}/fields

Add term reference field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Term reference",
  "machine_name": "ar_term_reference",
  "author": "hid_123456789",
  "type": "term_reference",
  "target": "{test_vocab_facets}"
}
```

===

Example output.

```json
{
  "message": "Field created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Field created"
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_term_reference}

## POST /types/{doc_type_machine_name}/facets

Add id field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "facets" :[
    "{field_term_reference}"
  ]
}
```

===

Example output.

```json
{
  "message": "Facets updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Facets updated"
* Data.facets[0]: "{field_term_reference}"

## POST /documents/{doc_type_endpoint}

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "{field_id}": 42,
  "{field_term_reference}": ["{test_term_uuid1}"]
}
```

===

Example output.

```json
{
  "message": "Test document facets created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document facets created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/{doc_type_endpoint}

Test filters.

* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "_count": 1,
  "_facets": [
    {
      "id": "{field_term_reference}",
      "label": "Term reference",
      "items": {
        "{test_term_uuid1}": {
          "filter": "{test_term_uuid1}",
          "label": "Display name for test term 1",
          "count": 1
        }
      }
    }
  ]
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc1}

## GET /documents/{doc_type_endpoint}

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[title]=Minimal

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc1}

## POST /documents/{doc_type_endpoint}

Add another document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Private",
  "author": "hid_123456789",
  "{field_id}": 42,
  "{field_term_reference}_label": "Test term 1"
}
```

===

Example output.

```json
{
  "message": "Test document facets created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document facets created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/{doc_type_endpoint}

Test facets.

* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "_count": 2,
  "_facets": [
    {
      "id": "{field_term_reference}",
      "label": "Term reference",
      "items": {
        "{test_term_uuid1}": {
          "filter": "{test_term_uuid1}",
          "label": "Display name for test term 1",
          "count": 2
        }
      }
    }
  ]
}
```

* Status: `200`
* Content-Type: "application/json"

## DELETE /documents/{doc_type_endpoint}/{doc1}

Delete document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /documents/{doc_type_endpoint}/{doc2}

Delete document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /types/{doc_type_machine_name}

Delete document type.

* Content-Type: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /vocabularies/{test_vocab_facets}/terms/{test_term_uuid1}

Delete term.

* Content-Type: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /vocabularies/{test_vocab_facets}/terms/{test_term_uuid2}

Delete term.

* Content-Type: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /vocabularies/{test_vocab_facets}

Delete vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
