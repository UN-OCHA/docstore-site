# Test webhooks endpoints

## POST /webhooks

Register a [webhook](https://webhook.site/#!/596df11a-21f8-4790-bb90-f79ba4ef9df6/6bbb526f-3610-459d-8c46-370aa8e9f695/1).

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My webhook",
  "payload_url": "https://webhook.site/596df11a-21f8-4790-bb90-f79ba4ef9df6"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Webhook created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {machine_name}

## POST /webhooks

Register again.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My webhook",
  "payload_url": "https://webhook.site/596df11a-21f8-4790-bb90-f79ba4ef9df6"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Webhook already exists"

## GET /webhooks

Get web hooks.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

```json
[]
```

* Status: `200`
* Content-Type: "application/json"

## GET /webhooks

Get web hooks.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].machine_name: "{machine_name}"


## DELETE /webhooks/{machine_name}

Delete web hooks.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

```json
{
  "message": "Webhook is not owned by you"
}
```

* Status: `403`
* Content-Type: "application/json"

## DELETE /webhooks/{machine_name}

Delete web hooks.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "message": "Webhook deleted"
}
```

* Status: `200`
* Content-Type: "application/json"

# Test webhook notifications

## POST /webhooks

Register a webhook.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My local webhook",
  "payload_url": "{WEBHOOK_SERVER_URL}",
  "events": [
    "document_type:create",
    "document_type:update",
    "document_type:delete",
    "vocabulary:create",
    "vocabulary:update",
    "vocabulary:delete"
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Webhook created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine name {webhook}

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test Vocabulary",
  "author": "test"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {vocabulary}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the vocabulary creation.

===

```json
[{
  "event": "vocabulary:create",
  "payload": "{vocabulary}"
}]
```

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_dpcument_type",
  "endpoint": "test-document-type",
  "label": "Test document type",
  "author": "test"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.machine_name: /.+/ // Machine name {doc_type}
* Data.endpoint: /.+/ // Endpoint {doc_type_endpoint}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document type creation

===

```json
[{
  "event": "document_type:create",
  "payload": "{doc_type}"
}]
```

## DELETE /webhooks/{webhook}

Delete webhook.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "message": "Webhook deleted"
}
```

* Status: `200`
* Content-Type: "application/json"

## POST /webhooks

Register a webhook.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My local webhook",
  "payload_url": "{WEBHOOK_SERVER_URL}",
  "events": [
    "document_type:create",
    "document_type:update",
    "document_type:delete",
    "vocabulary:create",
    "vocabulary:update",
    "vocabulary:delete",
    "field:document_type:create",
    "field:document_type:update",
    "field:document_type:delete",
    "field:vocabulary:create",
    "field:vocabulary:update",
    "field:vocabulary:delete",
    "document:create",
    "document:update",
    "document:delete",
    "document:{doc_type}:create",
    "document:{doc_type}:update",
    "document:{doc_type}:delete",
    "term:create",
    "term:update",
    "term:delete",
    "term:{vocabulary}:create",
    "term:{vocabulary}:update",
    "term:{vocabulary}:delete",
    "file:create",
    "file:update",
    "file:delete"
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Webhook created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine name {webhook}

## POST /vocabularies/{vocabulary}/fields

Add a field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test field",
  "author": "test",
  "type": "string"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Field created"
* Data.field_name: /^[0-9a-z_]+$/ // Field name {vocabulary_field}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the vocabulary field creation.

===

```json
[{
  "event": "field:vocabulary:create",
  "payload": {
    "field": "{vocabulary_field}",
    "vocabulary": "{vocabulary}"
  }
}]
```

## PATCH /vocabularies/{vocabulary}/fields/{vocabulary_field}

Update vocabulary field

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test field - Updated",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Field updated"

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the vocabulary field update.

===

```json
[{
  "event": "field:vocabulary:update",
  "payload": {
    "field": "{vocabulary_field}",
    "vocabulary": "{vocabulary}"
  }
}]
```

## DELETE /vocabularies/{vocabulary}/fields/{vocabulary_field}

Delete vocabulary field

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test field",
  "author": "test",
  "type": "string"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Field deleted"

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the vocabulary field deletion.

===

```json
[{
  "event": "field:vocabulary:delete",
  "payload": {
    "field": "{vocabulary_field}",
    "vocabulary": "{vocabulary}"
  }
}]
```

## POST /vocabularies/{vocabulary}/terms

Create a term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Pouet",
  "author": "test"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term uuid {term_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the term creation

===

```json
[{
  "event": "term:create",
  "payload": {
    "uuid": "{term_uuid}",
    "vocabulary": "{vocabulary}"
  }
},
{
  "event": "term:{vocabulary}:create",
  "payload": "{term_uuid}"
}]
```

## PATCH /vocabularies/{vocabulary}/terms/{term_uuid}

Update term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Machin"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: {term_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the term update.

===

```json
[{
  "event": "term:update",
  "payload": {
    "uuid": "{term_uuid}",
    "vocabulary": "{vocabulary}"
  }
},
{
  "event": "term:{vocabulary}:update",
  "payload": "{term_uuid}"
}]
```

## DELETE /vocabularies/{vocabulary}/terms/{term_uuid}

Delete term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term deleted"
* Data.uuid: {term_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the term deletion.

===

```json
[{
  "event": "term:delete",
  "payload": {
    "uuid": "{term_uuid}",
    "vocabulary": "{vocabulary}"
  }
},
{
  "event": "term:{vocabulary}:delete",
  "payload": "{term_uuid}"
}]
```

## PATCH /vocabularies/{vocabulary}

Update vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test vocabulary - Updated"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Vocabulary updated"

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the vocabulary update.

===

```json
[{
  "event": "vocabulary:update",
  "payload": "{vocabulary}"
}]
```

## DELETE /vocabularies/{vocabulary}

Delete vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the vocabulary deletion.

===

```json
[{
  "event": "vocabulary:delete",
  "payload": "{vocabulary}"
}]
```

## POST /types/{doc_type}/fields

Add a field to document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test field",
  "author": "test",
  "type": "string"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Field created"
* Data.field_name: /^[0-9a-z_]+$/ // Field name {doc_type_field}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document type field creation.

===

```json
[{
  "event": "field:document_type:create",
  "payload": {
    "field": "{doc_type_field}",
    "document_type": "{doc_type}"
  }
}]
```

## PATCH  /types/{doc_type}/fields/{doc_type_field}

Update document type field

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test field - Updated",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Field updated"

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document type field update.

===

```json
[{
  "event": "field:document_type:update",
  "payload": {
    "field": "{doc_type_field}",
    "document_type": "{doc_type}"
  }
}]
```

## DELETE  /types/{doc_type}/fields/{doc_type_field}

Delete document type field

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test field",
  "author": "test",
  "type": "string"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Field deleted"

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document type field deletion.

===

```json
[{
  "event": "field:document_type:delete",
  "payload": {
    "field": "{doc_type_field}",
    "document_type": "{doc_type}"
  }
}]
```

## POST /documents/{doc_type_endpoint}

Create a document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Pouet",
  "author": "test"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term uuid {doc_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document creation

===

```json
[{
  "event": "document:create",
  "payload": {
    "uuid": "{doc_uuid}",
    "type": "{doc_type}"
  }
},
{
  "event": "document:{doc_type}:create",
  "payload": "{doc_uuid}"
}]
```

## PATCH /documents/{doc_type_endpoint}/{doc_uuid}

Update document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Machin"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document update.

===

```json
[{
  "event": "document:update",
  "payload": {
    "uuid": "{doc_uuid}",
    "type": "{doc_type}"
  }
},
{
  "event": "document:{doc_type}:update",
  "payload": "{doc_uuid}"
}]
```

## DELETE /documents/{doc_type_endpoint}/{doc_uuid}

Delete document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document deletion.

===

```json
[{
  "event": "document:delete",
  "payload": {
    "uuid": "{doc_uuid}",
    "type": "{doc_type}"
  }
},
{
  "event": "document:{doc_type}:delete",
  "payload": "{doc_uuid}"
}]
```

## PATCH /types/{doc_type}

Update document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test document type - Updated"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Document type updated"

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document type update.

===

```json
[{
  "event": "document_type:update",
  "payload": "{doc_type}"
}]
```

## DELETE /types/{doc_type}

Delete document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the document type deletion.

===

```json
[{
  "event": "document_type:delete",
  "payload": "{doc_type}"
}]
```

## POST /files

Create a file.

Note: the data is "File content" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "filename":"webhook-file.txt",
  "mimetype":"text/plain",
  "data": "RmlsZSBjb250ZW50Cg=="
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the file creation.

===

```json
[{
  "event": "file:create",
  "payload": "{file_uuid}"
}]
```

## PATCH /files/{file_uuid}

Update file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "filename":"webhook-file-updated.txt"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File updated"
* Data.uuid: {file_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the file update.

===

```json
[{
  "event": "file:update",
  "payload": "{file_uuid}"
}]
```

## POST /files/{file_uuid}/content

Update file content.

* Content-Type: "text/plain"
* Accept: "application/json"
* API-KEY: abcd

```txt
File content - updated

```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File content created"
* Data.uuid: {file_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the file update.

===

```json
[{
  "event": "file:update",
  "payload": "{file_uuid}"
}]
```

## DELETE /files/{file_uuid}

Delete file.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File deleted"
* Data.uuid: {file_uuid}

## GET /wait

* API-KEY: abcd

===

* Status: `200`

## GET {WEBHOOK_SERVER_URL}

Check if we received the notification for the file deletion.

===

```json
[{
  "event": "file:delete",
  "payload": "{file_uuid}"
}]
```
