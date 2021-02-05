# Create documents

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_doc_bulk",
  "endpoint": "test-document-bulk",
  "label": "Test document bulk",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"

## POST /types/test_doc_bulk/fields

Add id field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My Id",
  "author": "test",
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

## POST /documents/test-document-bulk

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc1",
  "author": "hid_123456789",
  "metadata": [
    {
      "{field_id}": 42
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Test document bulk created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document bulk created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-bulk

Test filters.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## POST /documents/test-document-bulk/bulk

Add 2 documents.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "hid_123456789",
  "documents": [
    {
      "title": "Doc2",
      "metadata": [
        {
          "{field_id}": 42
        }
      ]
    },
    {
      "title": "Doc3",
      "metadata": [
        {
          "{field_id}": 42
        }
      ]
    }
  ]
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Test document bulk created"
* Data[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}
* Data[1].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc3}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-bulk/{doc3}

Get doc.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.title: "Doc3"

## DELETE /documents/test-document-bulk/{doc1}

Delete private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document bulk deleted"

## DELETE /documents/test-document-bulk/{doc2}

Delete private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document bulk deleted"

## DELETE /documents/test-document-bulk/{doc3}

Delete private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document bulk deleted"

## DELETE /types/test_doc_bulk

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

