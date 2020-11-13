# Create vocabularies

## POST /vocabularies

Create vocabulary without label.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "author": "hid_123456789"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## PUT /vocabularies/213313123

Update non-existing vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "label": "Organization",
  "description": "An example vocabulary"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## PATCH /vocabularies/dsfds-sdfsdf

Update non-existing vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "description": "A vocabulary for organizations"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## POST /vocabularies/435435435435/fields

Add iso3 field to non-existing vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "label": "ISO 3 code",
  "author": "hid_123456789",
  "type": "string"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## PATCH /vocabularies/43534535435/fields/xyzzy

Update non existing field.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "description": "Field does not exist"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## POST /vocabularies/xx/terms

Create term in non-existing vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "label": "Antwerp",
  "author": "23cdf322"
}
```

===

* Status: `403`
* Content-Type: "application/json"

## DELETE /vocabularies/xxx/fields/yyy

Delete field.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## DELETE /vocabularies/{uuid}

Delete vocabulary.

* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## DELETE /terms/12345

Delete term.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"

## PUT /terms/12345

Rename term.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "label": "Antwerp"
}
```

===

* Status: `403`
* Content-Type: "application/json"
