# Create vocabularies

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "City (anon test)",
  "machine_name": "anontest",
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

## POST /vocabularies/{machine_name}/fields

Add iso3 field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Display name",
  "machine_name": "display_name",
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_display_name}

## POST /vocabularies/{machine_name}/terms

Create city term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "display_name": "Antwerp",
  "author": "23cdf322",
  "{field_iso3}": "BEL"
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

Create city term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "New Amsterdam",
  "display_name": "New Amsterdam",
  "author": "23cdf322",
  "{field_iso3}": "USA"
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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_uuid_2}

## GET /vocabularies/xx-dd-sgd/fields

Get fields of unknown vocabulary.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## GET /vocabularies/{machine_name}/fields

Get fields.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## GET /vocabularies/{machine_name}/fields/{field_iso3}

Get field.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## `GET /vocabularies/{machine_name}/terms`

Get a vocabulary.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].label: "New Amsterdam"

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=new
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=new
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=new
* ?filter[label][operator]=starts_with

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=new
* ?filter[label][operator]=starts_with

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=amst
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=amst
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=nEw
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=nEw
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=nEw
* ?filter[label][operator]=starts_with

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=nEw
* ?filter[label][operator]=starts_with

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=Amst
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=Amst
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=nEw ams
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=nEw ams
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=nEw ams
* ?filter[label][operator]=starts_with

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=nEw ams
* ?filter[label][operator]=starts_with

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=label
* ?filter[label][value]=w Amst
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}

## `GET /vocabularies/{machine_name}/terms`

Test filters.

* Accept: "application/json"
* ?filter[label][path]=display_name
* ?filter[label][value]=w Amst
* ?filter[label][operator]=contains

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term_uuid_2}


## GET /vocabularies/{machine_name}/fields/{field_iso3}

Get field.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## `GET /vocabularies/{machine_name}`

Get a vocabulary.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /vocabularies/{machine_name}/terms/{term_uuid}

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

## DELETE /vocabularies/{machine_name}/terms/{term_uuid_2}

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

## DELETE /vocabularies/{machine_name}

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
