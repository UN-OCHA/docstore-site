# Test daterange

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "daterangetest",
  "endpoint": "daterange-test",
  "label": "daterange test document",
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

## POST /types/daterangetest/fields

Add daterange field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My daterange field",
  "machine_name": "my_daterange_field",
  "author": "common",
  "type": "daterange"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_daterange}

## POST /types/daterangetest/fields

Add multi value daterange field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My multi daterange field",
  "machine_name": "my_multi_daterange_field",
  "author": "common",
  "type": "daterange",
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_daterangemulti}

## POST /documents/daterange-test

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "{field_daterange}": {
    "start": "2021-04-15T01:00:00",
    "end": "2021-04-17T01:00:00"
  },
  "{field_daterangemulti}": [
    {
      "value": "2021-04-15T01:00:00",
      "end_value": "2021-04-17T01:00:00"
    },
    {
      "value": "2021-04-25T01:00:00",
      "end": "2021-04-27T01:00:00"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "daterange test document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "daterange test document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## POST /documents/daterange-test

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "{field_daterange}": {
    "start": "2021-05-15T01:00:00",
    "end": "2021-05-17T01:00:00"
  }
}
```

===

Example output.

```json
{
  "message": "daterange test document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "daterange test document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}

## POST /documents/daterange-test

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "private": true,
  "{field_daterangemulti}": [
    {
      "value": "2021-05-15T01:00:00",
      "end_value": "2021-05-17T01:00:00"
    },
    {
      "value": "2021-05-25T01:00:00",
      "end": "2021-05-27T01:00:00"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "daterange test document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "daterange test document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc3}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/daterange-test

Get docs.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/daterange-test/{doc1}

Get docs.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.my_daterange_field.start: "2021-04-15T01:00:00+00:00"
* Data.my_daterange_field.end: "2021-04-17T01:00:00+00:00"
* Data.my_multi_daterange_field[0].start: "2021-04-15T01:00:00+00:00"
* Data.my_multi_daterange_field[0].end: "2021-04-17T01:00:00+00:00"
* Data.my_multi_daterange_field[1].start: "2021-04-25T01:00:00+00:00"
* Data.my_multi_daterange_field[1].end: "2021-04-27T01:00:00+00:00"

## GET /documents/daterange-test/{doc2}

Get docs.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.my_daterange_field.start: "2021-05-15T01:00:00+00:00"
* Data.my_daterange_field.end: "2021-05-17T01:00:00+00:00"

## GET /documents/daterange-test/{doc3}

Get docs.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.my_multi_daterange_field[0].start: "2021-05-15T01:00:00+00:00"
* Data.my_multi_daterange_field[0].end: "2021-05-17T01:00:00+00:00"
* Data.my_multi_daterange_field[1].start: "2021-05-25T01:00:00+00:00"
* Data.my_multi_daterange_field[1].end: "2021-05-27T01:00:00+00:00"

## DELETE /types/daterangetest

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
