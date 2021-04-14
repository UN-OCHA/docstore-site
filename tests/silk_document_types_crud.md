# Create documents

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test",
  "endpoint": "test-documents",
  "label": "Test document",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"

## POST /types

Create document type with same machine name.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test",
  "endpoint": "test-documents",
  "label": "Test 2",
  "shared": true,
  "content_allowed": true,
  "fields_allowed": true,
  "author": "common",
  "allow_duplicates": true,
  "use_revisions": true,
}
```

===

* Status: `400`
* Content-Type: "application/json"

## POST /types

Create document type with same endpoint.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test2a",
  "endpoint": "test-documents",
  "label": "Test 2",
  "shared": true,
  "content_allowed": true,
  "fields_allowed": true,
  "author": "common",
  "allow_duplicates": true
}
```

===

* Status: `400`
* Content-Type: "application/json"

## POST /types

Create document type with illegal endpoint.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test2b",
  "endpoint": "me",
  "label": "Test 2",
  "shared": true,
  "content_allowed": true,
  "fields_allowed": true,
  "author": "common",
  "allow_duplicates": true
}
```

===

* Status: `400`
* Content-Type: "application/json"

## POST /types

Create document type with invalid endpoint.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test2c",
  "endpoint": "docuMents",
  "label": "Test 2",
  "shared": true,
  "content_allowed": true,
  "fields_allowed": true,
  "author": "common",
  "allow_duplicates": true
}
```

===

* Status: `400`
* Content-Type: "application/json"

## GET /types

Get document types.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /types/test

Get test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "machine_name": "test",
  "label": "Test document",
  "shared": true,
  "private": false,
  "content_allowed": true,
  "fields_allowed": true,
  "author":"common",
  "allow_duplicates":true,
  "endpoint":"test-documents"
}
```

* Status: `200`
* Content-Type: "application/json"

## PATCH /types/test

Update document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test document - private",
  "shared": false
}
```

===

* Status: `200`
* Content-Type: "application/json"

## GET /types/test

Get test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "machine_name": "test",
  "label": "Test document - private",
  "shared": false,
  "private": true,
  "content_allowed": true,
  "fields_allowed": true,
  "author":"common",
  "allow_duplicates":true,
  "endpoint":"test-documents"
}
```

* Status: `200`
* Content-Type: "application/json"

## GET /types/test/fields

Get test type fields.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## POST /types/test/fields

Add a field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "testfield",
  "label": "Test field",
  "author":"common",
  "type": "string"
}
```

===

* Status: `201`
* Content-Type: "application/json"

## GET /types/test/fields/testfield

Get test type field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## PATCH /types/test/fields/testfield

Add a field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test field - updated"
}
```

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /types/test/fields/testfield

Delete a field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /types/test

Get test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Content-Type: "application/json"

## DELETE /types/test

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

