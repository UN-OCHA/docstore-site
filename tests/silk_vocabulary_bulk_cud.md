# Create, update, delete terms in bulk

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Bulk CUD terms",
  "machine_name": "test_voc_bulk_cud",
  "author": "test",
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

## POST /vocabularies/{machine_name}/fields

Add iso3 field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "ISO 3 code",
  "author": "test",
  "type": "string",
  "machine_name": "test_voc_bulk_cud_field_iso3"
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


## POST /vocabularies/{machine_name}/terms/bulk

Create terms in bulk.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "terms": [
    {
      "label": "Term1",
      "{field_iso3}": "AFG"
    },
    {
      "label": "Term2",
      "{field_iso3}": "BEL"
    },
    {
      "label": "Term3",
      "{field_iso3}": "FRA"
    },
    {
      "label": "Term4",
      "{field_iso3}": "OPT"
    }
  ]
}
```

===

Expected output.

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Term created"
* Data[1].message: "Term created"
* Data[2].message: "Term created"
* Data[3].message: "Term created"
* Data[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term1 uuid {term_uuid1}
* Data[1].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term2 uuid {term_uuid2}
* Data[2].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term3 uuid {term_uuid3}
* Data[3].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term4 uuid {term_uuid4}

## POST /vocabularies/{machine_name}/terms/bulk

Try to create new terms with the same label.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "terms": [
    {
      "label": "Term1",
      "{field_iso3}": "AFG"
    },
    {
      "label": "Term2",
      "{field_iso3}": "BEL"
    }
  ]
}
```

===

Example output.

```json
[
  {
    "error": {
      "status": 400,
      "message": "Term with same label already exists"
    }
  },
  {
    "error": {
      "status": 400,
      "message": "Term with same label already exists"
    }
  }
]
```

* Status: `200`
* Content-Type: "application/json"


## PUT /vocabularies/{machine_name}/terms/bulk

Update terms in bulk. This is a full update so the `label` is mandatory.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "terms": [
    {
      "uuid": "{term_uuid1}",
      "label": "Term1 with new label",
      "{field_iso3}": "FFF"
    },
    {
      "uuid": "{term_uuid2}",
      "{field_iso3}": "GGGG"
    },
    {
      "uuid": "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa",
      "label": "Non existing term",
      "{field_iso3}": "nothing"
    }
  ]
}
```

===

Expected output.

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Term updated"
* Data[0].uuid: {term_uuid1}
* Data[1].error.status: 400
* Data[1].error.message: "Label is required"
* Data[2].error.status: 404
* Data[2].error.message: "Term does not exist"

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

## GET /vocabularies/{machine_name}/terms/{term_uuid1}

Check the `label` and `field_iso3` of the first term have been updated.

* Accept: "application/json"
* API-KEY: abcd

===

Expected output.

```json
{
  "uuid": "{term_uuid1}",
  "label": "Term1 with new label",
  "{field_iso3}": "FFF"
}
```

* Status: `200`
* Content-Type: "application/json"

## PATCH /vocabularies/{machine_name}/terms/bulk

Update (partially) terms in bulk.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "terms": [
    {
      "uuid": "{term_uuid2}",
      "{field_iso3}": "HHH"
    },
    {
      "uuid": "{term_uuid3}",
      "{field_iso3}": "III"
    }
  ]
}
```

===

Expected output.

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Term updated"
* Data[0].uuid: {term_uuid2}
* Data[1].message: "Term updated"
* Data[1].uuid: {term_uuid3}

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

## GET /vocabularies/{machine_name}/terms/{term_uuid3}

Check the `field_iso3` of the third term has been updated and that the
`label` is still the same.

* Accept: "application/json"
* API-KEY: abcd

===

Expected output.

```json
{
  "uuid": "{term_uuid3}",
  "label": "Term3",
  "{field_iso3}": "III"
}
```

* Status: `200`
* Content-Type: "application/json"

## DELETE /vocabularies/{machine_name}/terms/bulk

Delete elements in bulk.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "terms": [
    {
      "uuid": "{term_uuid3}"
    },
    {
      "uuid": "{term_uuid4}"
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
* Data[0].message: "Term deleted"
* Data[0].uuid: {term_uuid3}
* Data[1].message: "Term deleted"
* Data[1].uuid: {term_uuid4}
* Data[2].error.status: 404
* Data[2].error.message: "Term does not exist"

## GET /vocabularies/{machine_name}/terms/{term_uuid4}

Check that the fourth term doesn't exist anymore.

* Accept: "application/json"
* API-KEY: abcd

===

Expected output.

* Status: `404`
