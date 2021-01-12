# Create vocabularies

## POST /webhooks

Register a [webhook](https://webhook.site/#!/596df11a-21f8-4790-bb90-f79ba4ef9df6/6bbb526f-3610-459d-8c46-370aa8e9f695/1).

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Vocabulary CRUD test",
  "payload_url": "https://webhook.site/596df11a-21f8-4790-bb90-f79ba4ef9df6"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Webhook created"

## POST /vocabularies

Create vocabulary without label.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "hid_123456789"
}
```

===

Example output.

```json
{
  "message": "Label is required"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Label is required"

## POST /vocabularies

Create vocabulary without author.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "City"
}
```

===

Example output.

```json
{
  "message": "Author is required"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Author is required"

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "City",
  "author": "hid_123456789"
}
```

===

Example output.

```json
{
  "message": "Vocabulary created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {uuid}
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {machine_name}

## `GET /vocabularies/abc`

Get a non existing vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "message": "Vocabulary does not exist"
}
```

* Status: `404`
* Content-Type: "application/json"

## `GET /vocabularies/1321`

Get a non existing vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "message": "Vocabulary does not exist"
}
```

* Status: `404`
* Content-Type: "application/json"

## `GET /vocabularies/{machine_name}`

Get a vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "label": "City"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {uuid}
* Data.label: /./
* Data.machine_name: {machine_name}

## `GET /vocabularies/{uuid}`

Get a vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "label": "City"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.label: /./
* Data.uuid: {uuid}
* Data.machine_name: {machine_name}

## PUT /vocabularies/213313123

Update non-existing vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Organization",
  "description": "An example vocabulary"
}
```

===

Example output.

```json
{
  "message": "Vocabulary does not exist"
}
```

* Status: `404`
* Content-Type: "application/json"

## PUT /vocabularies/{machine_name}

Update vocabulary with too many fields.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Organization",
  "author": "hid_123456789",
  "description": "An example vocabulary"
}
```

===

Example output.

```json
{
  "message": "Field author does not exists"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Field author does not exists"

## PUT /vocabularies/{machine_name}

Update vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Organization",
  "description": "An example vocabulary"
}
```

===

Example output.

```json
{
  "message": "Vocabulary updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Vocabulary updated"
* Data.uuid: {uuid}

## `GET /vocabularies/{uuid}`

Get a vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "label": "Organization",
  "description": "An example vocabulary"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {uuid}
* Data.machine_name: {machine_name}

## PATCH /vocabularies/dsfds-sdfsdf

Update non-existing vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "description": "A vocabulary for organizations"
}
```

===

Example output.

```json
{
  "message": "Vocabulary does not exist"
}
```

* Status: `404`
* Content-Type: "application/json"

## PATCH /vocabularies/{uuid}

Update vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "description": "A vocabulary for organizations"
}
```

===

Example output.

```json
{
  "message": "Vocabulary updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Vocabulary updated"
* Data.uuid: {uuid}

## `GET /vocabularies/{uuid}`

Get a vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "label": "Organization",
  "description": "A vocabulary for organizations"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {uuid}
* Data.machine_name: {machine_name}

## POST /vocabularies/435435435435/fields

Add iso3 field to non-existing vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "ISO 3 code",
  "author": "hid_123456789",
  "type": "string"
}
```

===

Example output.

```json
{
  "message": "Vocabulary does not exist"
}
```

* Status: `404`
* Content-Type: "application/json"

## POST /vocabularies/{machine_name}/fields

Add iso3 field using wrong type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "ISO 3 code",
  "author": "hid_123456789",
  "type": "xyzzy"
}
```

===

Example output.

```json
{
  "message": "Unknown type"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Unknown type"

## POST /vocabularies/{machine_name}/fields

Add iso3 field without author.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "ISO 3 code",
  "type": "string"
}
```

===

Example output.

```json
{
  "message": "Author is required"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Author is required"

## POST /vocabularies/{machine_name}/fields

Add iso3 field without label.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "hid_123456789",
  "type": "string"
}
```

===

Example output.

```json
{
  "message": "Label is required"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Label is required"

## POST /vocabularies/{machine_name}/fields

Add iso3 field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "ISO 3 code",
  "author": "hid_123456789",
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_iso3}

## GET /vocabularies/xx-dd-sgd/fields

Get fields of unknown vocabulary.

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "message": "Vocabulary does not exist"
}
```

* Status: `404`
* Content-Type: "application/json"

## GET /vocabularies/{machine_name}/fields

Get fields.

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "uuid": "uuid",
  "name": "string",
  "description": "text_long",
  "author": "string",
  "provider_uuid": "entity_reference_uuid",
  "created": "timestamp",
  "silk_iso_3_code": "string"
}
```

* Status: `200`
* Content-Type: "application/json"

## PATCH /vocabularies/{machine_name}/fields/xyzzy

Update non existing field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "description": "Field does not exist"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Field does not exist"

## PATCH /vocabularies/{machine_name}/fields/{field_iso3}

Update field type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "description": "ISO3 term collection",
  "type": "integer"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Type can not be changed"

## PATCH /vocabularies/{machine_name}/fields/{field_iso3}

Update field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "description": "ISO3 term collection"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Field updated"

## GET /vocabularies/{machine_name}/fields/{field_iso3}

Get field.

* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "name": "silk_iso_3_code",
  "label": "ISO 3 code",
  "description": "ISO3 term collection",
  "type": "string",
  "multiple": false
}
```

* Status: `200`
* Content-Type: "application/json"

## POST /vocabularies/xx/terms

Create term in non-existing vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322"
}
```

===

Example output.

```json
{
  "message": "Vocabulary does not exist"
}
```

* Status: `404`
* Content-Type: "application/json"
* Data.message: "Vocabulary does not exist"

## POST /vocabularies/{machine_name}/terms

Create term without label.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "23cdf322"
}
```

===

Example output.

```json
{
  "message": "Label is required"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Label is required"

## POST /vocabularies/{machine_name}/terms

Create term without author.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp"
}
```

===

Example output.

```json
{
  "message": "Author is required"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Author is required"

## POST /vocabularies/{machine_name}/terms

Create term with wrong metadata.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322",
  "metadata": {
    "field": "not like this"
  }
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Metadata has to be an array"

## POST /vocabularies/{machine_name}/terms

Create term with wrong metadata.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322",
  "metadata": "not like this either"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Metadata has to be an array"

## POST /vocabularies/{machine_name}/terms

Create term with extra fields.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322",
  "metadata": [
    {
      "field": "3242342"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Field field does not exist"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Field field does not exist"

## POST /vocabularies/{machine_name}/terms

Create city term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322",
  "metadata": [
    {
      "{field_iso3}": "BEL"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_uuid}

## `GET /vocabularies/{machine_name}/terms`

Get a vocabulary.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].label: "Antwerp"

## DELETE /vocabularies/{machine_name}/fields/{field_iso3}

Delete field.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Field deleted"

## DELETE /vocabularies/{machine_name}/fields/{field_iso3}

Delete already deleted field.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Field does not exist"

## GET /vocabularies/{machine_name}/fields/{field_iso3}

Get field.

* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "message": "Field does not exist"
}
```

* Status: `400`
* Content-Type: "application/json"

## DELETE /vocabularies/{uuid}

Delete vocabulary.

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "message": "Vocabulary is in use and can not be deleted"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Vocabulary is in use and can not be deleted"

## DELETE /terms/{term_uuid}

Delete term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "message": "Term deleted"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term deleted"

## DELETE /terms/{term_uuid}

Delete deleted term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

* Status: `404`
* Content-Type: "application/json"
* Data.message: "Term does not exist"

## DELETE /vocabularies/{uuid}

Delete vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "message": "Vocabulary deleted"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Vocabulary deleted"

## `GET /vocabularies/{machine_name}`

Get a vocabulary.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## POST /vocabularies

Create vocabulary disallowing duplicate terms.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Dupes",
  "author": "hid_123456789",
  "allow_duplicates": false
}
```

===

Example output.

```json
{
  "message": "Vocabulary created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {uuid}
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {machine_name}

## POST /vocabularies/{machine_name}/terms

Create term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_uuid}

## POST /vocabularies/{machine_name}/terms

Create term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "London",
  "author": "23cdf322"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"

## POST /vocabularies/{machine_name}/terms

Create term again.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Term with same label already exists"

## PUT /terms/{term_uuid}

Rename term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"

## PUT /terms/{term_uuid}

Rename term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "London"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Term with same label already exists"

## POST /vocabularies

Create vocabulary allowing duplicate terms.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Dupes allowed",
  "author": "hid_123456789"
}
```

===

Example output.

```json
{
  "message": "Vocabulary created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {uuid}
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {machine_name}

## POST /vocabularies/{machine_name}/terms

Create term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_uuid}

## POST /vocabularies/{machine_name}/terms

Create term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "London",
  "author": "23cdf322"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"

## POST /vocabularies/{machine_name}/terms

Create term again.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"

## PUT /terms/{term_uuid}

Rename term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"

## PUT /terms/{term_uuid}

Rename term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "London"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"

## PUT /vocabularies/{machine_name}

Update vocabulary to disallow duplicate terms.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Dupes no longer allowed",
  "allow_duplicates": false
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Vocabulary contains duplicate terms"

## DELETE /terms/{term_uuid}

Delete term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term deleted"

## PUT /vocabularies/{machine_name}

Update vocabulary to disallow duplicate terms.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Dupes no longer allowed",
  "allow_duplicates": false
}
```

===

Example output.

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Vocabulary updated"
