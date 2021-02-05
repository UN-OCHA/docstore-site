# Create terms in bulk

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Bulk terms",
  "machine_name": "voc_bulk",
  "author": "test"
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

## `GET /vocabularies/{machine_name}`

Get a vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "label": "Bulk terms"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {uuid}
* Data.label: /./

## POST /vocabularies/{machine_name}/fields

Add iso3 field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "ISO 3 code",
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_iso3}

## POST /vocabularies/{machine_name}/terms

Create city term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Term1",
  "author": "Author1",
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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_uuid1}

## `GET /vocabularies/{machine_name}/terms`

Get terms.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].label: "Term1"

## POST /vocabularies/{machine_name}/terms/bulk

Create city terms.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author1",
  "terms": [
    {
      "label": "Term2",
      "metadata": [
        {
          "{field_iso3}": "BEL"
        }
      ]
    },
    {
      "label": "Term3",
      "metadata": [
        {
          "{field_iso3}": "BEL"
        }
      ]
    }
  ]
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_uuid2}
* Data[1].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_uuid3}

## DELETE /vocabularies/{uuid}/terms/{term_uuid1}

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

## DELETE /vocabularies/{uuid}/terms/{term_uuid2}

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

## DELETE /vocabularies/{uuid}/terms/{term_uuid3}

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
