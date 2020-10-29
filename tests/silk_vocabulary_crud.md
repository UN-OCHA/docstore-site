# Create vocabularies

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

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {uuid}
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {machine_name}

## `GET /vocabularies/{machine_name}`

Get a vocabulary.

* Content-Type: "application/json"
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

* Content-Type: "application/json"
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

* Content-Type: "application/json"
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

* Content-Type: "application/json"
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

## POST /vocabularies/{machine_name}/terms

Create city term.

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

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_uuid}

## `GET /vocabularies/{machine_name}/terms`

Get a vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].label: "Antwerp"

## DELETE /vocabularies/{uuid}

Delete vocabulary.

* Content-Type: "application/json"
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

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## `GET /terms`

Get all terms.

* Content-Type: "application/json"
* Accept: "application/json"

===

Example output.

```json
[]
```

* Status: `200`
* Content-Type: "application/json"
