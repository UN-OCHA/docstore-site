# Create, update, delete documents in bulk

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
      "_action": "create",
      "title": "Doc1",
      "metadata": [
        {
          "{field_id}": "doc1"
        }
      ]
    },
    {
      "_action": "create",
      "title": "Doc2",
      "metadata": [
        {
          "{field_id}": "doc2"
        }
      ]
    },
    {
      "_action": "create",
      "title": "Doc3",
      "metadata": [
        {
          "{field_id}": "doc3"
        }
      ]
    },
    {
      "_action": "create",
      "title": "Doc4",
      "metadata": [
        {
          "{field_id}": "doc4"
        }
      ]
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

## POST /documents/test-document-bulk-cud/bulk

Update an existing document, delete an existing document, create new document
and attempt to update a non existing document in same request.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "documents": [
    {
      "_action": "update",
      "uuid": "{doc_uuid1}",
      "title": "Doc1 with new label",
      "metadata": [
        {
          "{field_id}": "doc1_new"
        }
      ]
    },
    {
      "_action": "delete",
      "uuid": "{doc_uuid4}"
    },
    {
      "_action": "create",
      "title": "Doc5",
      "metadata": [
        {
          "{field_id}": "doc5"
        }
      ]
    },
    {
      "_action": "update",
      "uuid": "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa",
      "title": "Non existing document",
      "metadata": [
        {
          "{field_id}": "nothing"
        }
      ]
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
* Data[1].message: "Test document bulk CUD deleted"
* Data[1].uuid: {doc_uuid4}
* Data[2].message: "Test document bulk CUD created"
* Data[2].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Doc5 uuid {doc_uuid5}
* Data[3].error.status: 404
* Data[3].error.message: "Document does not exist"
