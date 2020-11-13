# Create vocabularies

## GET /vocabularies/xx-dd-sgd/fields

Get fields of unknown vocabulary.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## GET /vocabularies/silk_city/fields

Get fields.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## GET /vocabularies/silk_city/fields/{field_iso3}

Get field.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## `GET /vocabularies/silk_city/terms`

Get a vocabulary.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].label: "Antwerp"

## GET /vocabularies/silk_city/fields/{field_iso3}

Get field.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## `GET /vocabularies/silk_city`

Get a vocabulary.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## `GET /terms`

Get all terms.

* Accept: "application/json"

===

Example output.

```json
[]
```

* Status: `200`
* Content-Type: "application/json"
