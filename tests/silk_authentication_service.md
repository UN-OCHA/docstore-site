# Create documents

## POST /types?api-key=abcd

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "machine_name": "doc_auth_service",
  "endpoint": "doc-auth-service",
  "label": "Test document bulk",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"

## GET /types

Get document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: xyzzy

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /types/doc_auth_service

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
