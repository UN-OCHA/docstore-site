# Create vocabularies

## POST /vocabularies

Create vocabulary.

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
  "message": "Vocabulary created"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {city}

## `GET /vocabularies/{city}`

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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.label: /./
* Data.machine_name: {city}

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Organization"
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

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {organization}

## `GET /vocabularies/{organization}`

Get a vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"

===

Example output.

```json
{
  "label": "Organization"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.label: /./
* Data.machine_name: {organization}

# Create terms

Create terms.

## POST /terms

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "vocabulary": "{city}"
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

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {city_antwerp}

## POST /terms

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Brussels",
  "vocabulary": "{city}"
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

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {city_brussels}

## POST /terms

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Borgerhout",
  "vocabulary": "{city}"
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

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {city_borgerhout}

## GET /terms/{city_borgerhout}

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Borgerhout",
  "vocabulary": "{city}"
}
```

===

Example output.

```json
{
  "label": "Borgerhout",
  "vocabulary_name": "{city}",
  "langcode": "en",
  "status": "1",
  "name": "Borgerhout"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.label: /./
* Data.vocabulary_name: {city}
