# Create documents

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "geotest",
  "endpoint": "geo-test",
  "label": "Geo test document",
  "shared": true,
  "content_allowed": true,
  "fields_allowed": true,
  "author": "common",
  "allow_duplicates": true
}
```

===

* Status: `201`
* Content-Type: "application/json"

## POST /types/geotest/fields

Add geo field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My Geo field",
  "author": "common",
  "type": "geofield"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_geo}

## POST /types/geotest/fields

Add multi value geo field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My multi Geo field",
  "author": "common",
  "type": "geofield",
  "multiple": 1
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_geomulti}

## POST /documents/geo-test

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
      "{field_geo}": {
        "lat": 4.6,
        "lon": 51,
        "value": "POINT (4.6 51)"
      },
      "{field_geomulti}": [
        {
          "lat": 4.6,
          "lon": 51,
          "value": "POINT (4.6 51)"
        },
        {
          "lat": 7,
          "lon": 38,
          "value": "POINT (7 38)"
        }
      ]
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Geo test document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Geo test document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## POST /documents/geo-test

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
      "{field_geo}": {
        "lat": 4.6,
        "lon": 51,
        "value": "POINT (4.6 51)"
      }
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Geo test document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Geo test document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}

## POST /documents/geo-test

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "private": true,
  "metadata": [
    {
      "{field_geomulti}": [
        {
          "lat": 4.6,
          "lon": 51,
          "value": "POINT (4.6 51)"
        },
        {
          "lat": 7,
          "lon": 38,
          "value": "POINT (7 38)"
        }
      ]
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Geo test document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Geo test document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc3}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/geo-test

Get docs.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## DELETE /types/geotest

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
