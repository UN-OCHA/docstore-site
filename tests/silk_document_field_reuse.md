# Create documents

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_doc_use_1",
  "endpoint": "test-doc-use-one",
  "label": "Test document reuse 1",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.machine_name: /.+/ // Endpoint {doc_type_machine_name_1}
* Data.endpoint: /.+/ // Endpoint {doc_type_endpoint_1}

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_doc_use_2",
  "endpoint": "test-doc-use-two",
  "label": "Test document reuse 2",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.machine_name: /.+/ // Endpoint {doc_type_machine_name_2}
* Data.endpoint: /.+/ // Endpoint {doc_type_endpoint_2}

## POST /vocabularies

Add test_vocab_facets.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_vocab_use_1",
  "label": "Test Vocab reuse 1",
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
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {test_vocab_use_1}

## POST /vocabularies

Add test_vocab_facets.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_vocab_use_2",
  "label": "Test Vocab reuse 2",
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
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {test_vocab_use_2}

## POST /types/{doc_type_machine_name_1}/fields

Add id field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My Id",
  "machine_name": "my_id",
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

## POST /types/{doc_type_machine_name_2}/fields

Add same id field to other content type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My Id",
  "machine_name": "my_id",
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

## POST /types/{doc_type_machine_name_1}/fields

Add id field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Single value",
  "machine_name": "my_single",
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

## POST /types/{doc_type_machine_name_2}/fields

Add same id field to other content type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Single value",
  "machine_name": "my_single",
  "multiple": true,
  "author": "hid_123456789",
  "type": "integer"
}
```

===

Example output.

```json
{
  "message": "Field my_single already exists, unable to change cardinality"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Field my_single already exists, unable to change cardinality"

## POST /types/{doc_type_machine_name_1}/fields

Add id field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Single value",
  "machine_name": "my_int_field",
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

## POST /types/{doc_type_machine_name_2}/fields

Add same id field to other content type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Single value",
  "machine_name": "my_int_field",
  "author": "hid_123456789",
  "type": "string"
}
```

===

Example output.

```json
{
  "message": "Field my_int_field already exists, unable to change field type"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Field my_int_field already exists, unable to change field type"

## DELETE /types/{doc_type_machine_name_1}

Delete document type.

* Content-Type: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /types/{doc_type_machine_name_2}

Delete document type.

* Content-Type: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /vocabularies/{test_vocab_use_1}

Delete vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /vocabularies/{test_vocab_use_2}

Delete vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
