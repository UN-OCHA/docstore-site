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

## DELETE /types/test

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

