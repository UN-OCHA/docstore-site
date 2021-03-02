# Create assessments

## POST /types/assessments/fields

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

## POST /types/assessments/fields

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

## POST /types/assessments/fields

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

## POST /types/assessments/fields

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

## POST /types/assessments/fields

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

## POST /documents/assessments

Add a assessment as anonymous.

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

## POST /documents/assessments

Add a assessment without title.

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

## POST /documents/assessments

Add a assessment without author.

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

## POST /documents/assessments

Add a minimal assessment.

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
  "message": "Assessment created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Assessment created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/assessments

Test filters.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents/assessments

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[title]=Minimal

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents/assessments

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[silk_my_id]=42

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents/assessments

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

## GET /documents/assessments

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

## GET /documents/assessments

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

## POST /documents/assessments

Add a private assessment.

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
  "message": "Assessment created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Assessment created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## POST /documents/assessments

Add an unpublished assessment.

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
  "message": "Assessment created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Assessment created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc3}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## POST /documents/assessments

Add an unpublished private assessment.

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
  "message": "Assessment created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Assessment created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc4}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/assessments/{doc2}

Get private assessment as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/assessments/{doc2}

Get private assessment as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc2}

Get private assessment as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc3}

Get unpublished assessment as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}

## GET /documents/assessments/{doc3}

Get unpublished assessment as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc3}

Get unpublished assessment as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc4}

Get private unpublished assessment as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc4}

## GET /documents/assessments/{doc4}

Get unpublished assessment as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc4}

Get unpublished assessment as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## PUT /documents/assessments/{doc1}

Update minimal assessment.

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
  "message": "Assessment updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Assessment updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/assessments/{doc1}

Get minimal assessment.

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

## PUT /documents/assessments/{doc1}

Update minimal assessment as anonymous.

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

## PUT /documents/assessments/{doc1}

Update minimal assessment as other provider.

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

## PATCH /documents/assessments/{doc2}

Update private assessment.

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
  "message": "Assessment updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Assessment updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"


## GET /documents/assessments

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[silk_my_id]=7

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc2}

## GET /documents/assessments/{doc2}

Get Private assessment.

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

## PATCH /documents/assessments/{doc2}

Update private assessment as anonymous.

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

## PATCH /documents/assessments/{doc2}

Update private assessment as other provider.

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

## GET /documents/assessments/{doc2}

Get private assessment as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/assessments/{doc2}

Get private assessment as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc2}

Get private assessment as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## PATCH /documents/assessments/{doc2}

Make private assessment public.

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
  "message": "Assessment updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Assessment updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/assessments/{doc2}

Get Private assessment.

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

## GET /documents/assessments/{doc2}

Get private assessment as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/assessments/{doc2}

Get private assessment as anonymous.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## GET /documents/assessments/{doc2}

Get private assessment as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc2}

## PATCH /documents/assessments/{doc3}

Make unpublished assessment public.

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
  "message": "Assessment updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Assessment updated"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/assessments/{doc3}

Get unpublished assessment.

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

## GET /documents/assessments/{doc3}

Get unpublished assessment as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}

## GET /documents/assessments/{doc3}

Get unpublished assessment as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc3}

Get unpublished assessment as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## DELETE /documents/assessments/{doc3}

Delete private assessment as anonymous.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## DELETE /documents/assessments/{doc3}

Delete private assessment as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `400`
* Content-Type: "application/json"

## DELETE /documents/assessments/{doc3}

Delete private assessment as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Assessment deleted"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/assessments/{doc3}

Get deleted unpublished assessment as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc3}

Get deleted unpublished assessment as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/assessments/{doc3}

Get deleted unpublished assessment as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"
