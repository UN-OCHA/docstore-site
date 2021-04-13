# Create documents

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_doc_crud",
  "endpoint": "test-document-crud",
  "label": "Test document CRUD",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"

## POST /types/test_doc_crud/fields

Test empty post.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You have to pass a JSON object"

## POST /types/test_doc_crud/fields

Test empty post.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You have to pass a JSON object"

## POST /types/test_doc_crud/fields

Test empty post.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You have to pass a JSON object"

## POST /types/test_doc_crud/fields

Test illegal json post.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
345345345
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You have to pass a JSON object"

## POST /types/test_doc_crud/fields

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

## POST /documents/test-document-crud

Add a document as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
}
```

===

* Status: `403`
* Content-Type: "application/json"

## POST /documents/test-document-crud

Add a document without title.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "hid_123456789"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Title is required"

## POST /documents/test-document-crud

Add a document without author.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc with term, no files"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Author is required"

## POST /documents/test-document-crud

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "{field_id}": 42
}
```

===

Example output.

```json
{
  "message": "Test document CRUD created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document CRUD created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud

Test filters.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc1}

## GET /documents/test-document-crud

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[title]=Minimal

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc1}

## GET /documents/test-document-crud

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[silk_my_id]=42

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc1}

## GET /documents/test-document-crud

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[title]=Not

===

```json
{
  "_count": 0,
  "results": []
}
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[f1][group][conjunction]=AND
* ?filter[p1][condition][path]=title
* ?filter[p1][condition][value]=Minimal

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc1}

## GET /documents/test-document-crud

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[f1][group][conjunction]=AND
* ?filter[p1][condition][path]=title
* ?filter[p1][condition][value]=Not
* ?filter[p1][condition][value]=Not

===

```json
{
  "_count": 0,
  "results": []
}
```

* Status: `200`
* Content-Type: "application/json"

## POST /documents/test-document-crud

Add a private document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Private",
  "author": "hid_123456789",
  "private": true,
  "{field_id}": 42
}
```

===

Example output.

```json
{
  "message": "Test document CRUD created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document CRUD created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## POST /documents/test-document-crud

Add an unpublished document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Unpublished",
  "author": "hid_123456789",
  "published": false,
  "{field_id}": 42
}
```

===

Example output.

```json
{
  "message": "Test document CRUD created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document CRUD created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc3}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## POST /documents/test-document-crud

Add an unpublished private document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Private unpublished",
  "author": "hid_123456789",
  "published": false,
  "private": true,
  "{field_id}": 42
}
```

===

Example output.

```json
{
  "message": "Test document CRUD created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document CRUD created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc4}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc2}

Get private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/test-document-crud/{doc2}

Get private document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc2}

Get private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc3}

Get unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}

## GET /documents/test-document-crud/{doc3}

Get unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc3}

Get unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc4}

Get private unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc4}

## GET /documents/test-document-crud/{doc4}

Get unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc4}

Get unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## PUT /documents/test-document-crud/{doc1}

Update minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal - updated"
}
```

===

Example output.

```json
{
  "message": "Test document CRUD updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document CRUD updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc1}

Get minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "title": "Minimal - updated"
}
```

* Status: `200`
* Content-Type: "application/json"

## PUT /documents/test-document-crud/{doc1}

Update minimal document as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "title": "Minimal - updated anonymous"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## PUT /documents/test-document-crud/{doc1}

Update minimal document as other provider.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "title": "Minimal - updated other"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## PATCH /documents/test-document-crud/{doc2}

Update private document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Private - updated",
  "{field_id}": 7
}
```

===

Example output.

```json
{
  "message": "Test document CRUD updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document CRUD updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[silk_my_id]=7

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc2}

## GET /documents/test-document-crud/{doc2}

Get Private document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "title": "Private - updated"
}
```

* Status: `200`
* Content-Type: "application/json"

## PATCH /documents/test-document-crud/{doc2}

Update private document as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "title": "Private - updated anonymous"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## PATCH /documents/test-document-crud/{doc2}

Update private document as other provider.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "title": "Private - updated other"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc2}

Get private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/test-document-crud/{doc2}

Get private document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc2}

Get private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## PATCH /documents/test-document-crud/{doc2}

Make private document public.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Private - made public",
  "private": false
}
```

===

Example output.

```json
{
  "message": "Test document CRUD updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document CRUD updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc2}

Get Private document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "title": "Private - made public"
}
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc2}

Get private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/test-document-crud/{doc2}

Get private document as anonymous.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/test-document-crud/{doc2}

Get private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## PATCH /documents/test-document-crud/{doc3}

Make unpublished document public.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Unpublished - made public",
  "published": true
}
```

===

Example output.

```json
{
  "message": "Test document CRUD updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document CRUD updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc3}

Get unpublished document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "title": "Unpublished - made public"
}
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc3}

Get unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}

## GET /documents/test-document-crud/{doc3}

Get unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc3}

Get unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /documents/test-document-crud/{doc3}

Delete private document as anonymous.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## DELETE /documents/test-document-crud/{doc3}

Delete private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Content-Type: "application/json"

## DELETE /documents/test-document-crud/{doc3}

Delete private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document CRUD deleted"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc3}

Get deleted unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc3}

Get deleted unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/test-document-crud/{doc3}

Get deleted unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## POST /types/test_doc_crud/fields

Add required field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Needed",
  "author": "hid_123456789",
  "type": "integer",
  "required": true
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_needed}

## POST /documents/test-document-crud

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "{field_id}": 42
}
```

===

Example output.

```json
{
  "message": "Unable to save resource: This value should not be null. (silk_needed)"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Unable to save resource: This value should not be null. (silk_needed)"

## POST /documents/test-document-crud

Add a minimal document with a non-existing field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "unknown_field": 42
}
```

===

Example output.

```json
{
  "message": "Field unknown_field does not exist"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Field unknown_field does not exist"

## POST /documents/test-document-crud

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "{field_needed}": 42
}
```

===

Example output.

```json
{
  "message": "Test document CRUD created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document CRUD created"

## POST /documents/test-document-crud

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "{field_needed}": "This is not an integer"
}
```

===

Example output.

```json
{
  "message": "Unable to save resource: This value should be of the correct primitive type. (silk_needed.0.value)"
}
```

* Content-Type: "application/json"
* Data.message: "Unable to save resource: This value should be of the correct primitive type. (silk_needed.0.value)"

## DELETE /types/test_doc_crud

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
