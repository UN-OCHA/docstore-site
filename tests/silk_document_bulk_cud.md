# Create, update, delete documents in bulk

## DELETE /types/test_doc_bulk_cud

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Content-Type: "application/json"

## POST /types

Create a document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_doc_bulk_cud",
  "endpoint": "test-document-bulk-cud",
  "label": "Test document bulk CUD",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"

## POST /types/test_doc_bulk_cud/fields

Add id field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My Id",
  "author": "test",
  "type": "string",
  "machine_name": "test_doc_bulk_cud_field_id"
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

## POST /documents/test-document-bulk-cud/bulk

Create documents in bulk.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "documents": [
    {
      "title": "Doc1",
      "{field_id}": "doc1"
    },
    {
      "title": "Doc2",
      "{field_id}": "doc2"
    },
    {
      "title": "Doc3",
      "{field_id}": "doc3"
    },
    {
      "title": "Doc4",
      "{field_id}": "doc4"
    }
  ]
}
```

===

Expected output.

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Test document bulk CUD created"
* Data[1].message: "Test document bulk CUD created"
* Data[2].message: "Test document bulk CUD created"
* Data[3].message: "Test document bulk CUD created"
* Data[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Doc1 uuid {doc_uuid1}
* Data[1].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Doc2 uuid {doc_uuid2}
* Data[2].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Doc3 uuid {doc_uuid3}
* Data[3].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Doc4 uuid {doc_uuid4}

## PUT /documents/test-document-bulk-cud/bulk

Update documents in bulk. This is a full update so the `title` is mandatory.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "documents": [
    {
      "uuid": "{doc_uuid1}",
      "title": "Doc1 with new label",
      "{field_id}": "doc1_new"
    },
    {
      "uuid": "{doc_uuid2}",
      "{field_id}": "doc2_new"
    },
    {
      "uuid": "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa",
      "title": "Non existing document",
      "{field_id}": "nothing"
    }
  ]
}
```

===

Expected output.

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Test document bulk CUD updated"
* Data[0].uuid: {doc_uuid1}
* Data[1].error.status: 400
* Data[1].error.message: "Title is required"
* Data[2].error.status: 404
* Data[2].error.message: "Document does not exist"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-bulk-cud/{doc_uuid1}

Check the `title` and `field_id` of the first document have been updated.

* Accept: "application/json"
* API-KEY: abcd

===

Expected output.

```json
{
  "uuid": "{doc_uuid1}",
  "title": "Doc1 with new label",
  "{field_id}": "doc1_new"
}
```

* Status: `200`
* Content-Type: "application/json"

## PATCH /documents/test-document-bulk-cud/bulk

Update (partially) documents in bulk.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "documents": [
    {
      "uuid": "{doc_uuid2}",
      "{field_id}": "doc2_new"
    },
    {
      "uuid": "{doc_uuid3}",
      "{field_id}": "doc3_new"
    },
    {
      "{field_id}": "doc3_new"
    }
  ]
}
```

===

Expected output.

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Test document bulk CUD updated"
* Data[0].uuid: {doc_uuid2}
* Data[1].message: "Test document bulk CUD updated"
* Data[1].uuid: {doc_uuid3}
* Data[2].error.message: "Document id is required"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-bulk-cud/{doc_uuid3}

Check the `field_id` of the third document has been updated and that the
`title` is still the same.

* Accept: "application/json"
* API-KEY: abcd

===

Expected output.

```json
{
  "uuid": "{doc_uuid3}",
  "title": "Doc3",
  "{field_id}": "doc3_new"
}
```

* Status: `200`
* Content-Type: "application/json"

## DELETE /documents/test-document-bulk-cud/bulk

Delete elements in bulk.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "documents": [
    {
      "uuid": "{doc_uuid3}"
    },
    {
      "uuid": "{doc_uuid4}"
    },
    {
      "uuid": "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa"
    }
  ]
}
```

===

Expected output.

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Test document bulk CUD deleted"
* Data[0].uuid: {doc_uuid3}
* Data[1].message: "Test document bulk CUD deleted"
* Data[1].uuid: {doc_uuid4}
* Data[2].error.status: 404
* Data[2].error.message: "Document does not exist"

## GET /documents/test-document-bulk-cud/{doc_uuid4}

Check that the fourth document doesn't exist anymore.

* Accept: "application/json"
* API-KEY: abcd

===

Expected output.

* Status: `404`

## POST /documents/test-document-bulk-cud/bulk

Create documents in bulk without author.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "documents": [
    {
      "title": "Doc1",
      "{field_id}": "doc1"
    },
    {
      "title": "Doc2",
      "{field_id}": "doc2"
    },
    {
      "title": "Doc3",
      "{field_id}": "doc3"
    },
    {
      "title": "Doc4",
      "{field_id}": "doc4"
    }
  ]
}
```

===

Expected output.

* Status: `400`
* Content-Type: "application/json"

## POST /documents/test-document-bulk-cud/bulk

Create documents in bulk without documents.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author"
}
```

===

Expected output.

* Status: `400`
* Content-Type: "application/json"

## POST /documents/test-document-bulk-cud/bulk

Create documents in bulk without documents.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "documents": "Not a string"
}
```

===

Expected output.

* Status: `400`
* Content-Type: "application/json"

## DELETE /types/test_doc_bulk_cud

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
