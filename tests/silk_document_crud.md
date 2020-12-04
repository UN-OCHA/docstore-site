# Create documents

## POST /fields/documents

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

## POST /fields/documents

Test empty post.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
[]
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You have to pass a JSON object"

## POST /fields/documents

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

## POST /fields/documents

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

## POST /fields/documents

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

## POST /fields/documents

Add country field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Country",
  "author": "hid_123456789",
  "type": "entity_reference_uuid",
  "target": "shared_countries"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_country}

## POST /documents

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

## POST /documents

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

## POST /documents

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

## POST /documents

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
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
  "message": "Document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[title]=Minimal

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[silk_my_id]=42

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[title]=Not

===

```json
[]
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[f1][group][conjunction]=AND
* ?filter[p1][condition][path]=title
* ?filter[p1][condition][value]=Minimal

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[f1][group][conjunction]=AND
* ?filter[p1][condition][path]=title
* ?filter[p1][condition][value]=Not
* ?filter[p1][condition][value]=Not

===

```json
[]
```

* Status: `200`
* Content-Type: "application/json"

## POST /documents

Add a private document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Private",
  "author": "hid_123456789",
  "private": true,
  "metadata": [
    {
      "{field_id}": 42,
      "{field_country}_label": "Aruba"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## POST /documents

Add an unpublished document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Unpublished",
  "author": "hid_123456789",
  "published": false,
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
  "message": "Document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc3}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## POST /documents

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
  "message": "Document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc4}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/{doc2}

Get private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}
* Data.silk_country[0].name: "Aruba"

## GET /documents/{doc2}

Get private document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc2}

Get private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc3}

Get unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}

## GET /documents/{doc3}

Get unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc3}

Get unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc4}

Get private unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc4}

## GET /documents/{doc4}

Get unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc4}

Get unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## PUT /documents/{doc1}

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
  "message": "Document updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Document updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/{doc1}

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

## PUT /documents/{doc1}

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

## PUT /documents/{doc1}

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

## PATCH /documents/{doc2}

Update private document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Private - updated",
  "metadata": [
    {
      "{field_id}": 7
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Document updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Document updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"


## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[silk_my_id]=7

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc2}

## GET /documents/{doc2}

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

## PATCH /documents/{doc2}

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

## PATCH /documents/{doc2}

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

## GET /documents/{doc2}

Get private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/{doc2}

Get private document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc2}

Get private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## PATCH /documents/{doc2}

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
  "message": "Document updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Document updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/{doc2}

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

## GET /documents/{doc2}

Get private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/{doc2}

Get private document as anonymous.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/{doc2}

Get private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## PATCH /documents/{doc3}

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
  "message": "Document updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Document updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/{doc3}

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

## GET /documents/{doc3}

Get unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}

## GET /documents/{doc3}

Get unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc3}

Get unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## DELETE /documents/{doc3}

Delete private document as anonymous.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## DELETE /documents/{doc3}

Delete private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `400`
* Content-Type: "application/json"

## DELETE /documents/{doc3}

Delete private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Document deleted"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/{doc3}

Get deleted unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc3}

Get deleted unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc3}

Get deleted unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## POST /fields/documents

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

## POST /documents

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
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
  "message": "Unable to save document: This value should not be null. (silk_needed)"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Unable to save document: This value should not be null. (silk_needed)"

## POST /documents

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "metadata": [
    {
      "{field_needed}": 42
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"

## POST /documents

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "metadata": [
    {
      "{field_needed}": "This is not an integer"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Unable to save document: This value should be of the correct primitive type. (silk_needed.0.value)"
}
```

* Content-Type: "application/json"
* Data.message: "Unable to save document: This value should be of the correct primitive type. (silk_needed.0.value)"

