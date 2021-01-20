# Test link field

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "linktest",
  "endpoint": "link-test",
  "label": "link test document",
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

## POST /fields/link-test

Add link field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My link field",
  "author": "common",
  "type": "link"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_link}

## POST /fields/link-test

Add multi value link field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My multi link field",
  "author": "common",
  "type": "link",
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_linkmulti}

## POST /link-test

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
      "{field_link}": {
        "uri": "https://attiks.com",
        "title": "Print and web design"
      },
      "{field_linkmulti}": [
        {
          "uri": "https://attiks.com",
          "title": "Print and web design"
        },
        {
          "uri": "https://unocha.org",
          "title": "UNOCHA"
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
  "message": "Linktest created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Linktest created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## POST /link-test

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
      "{field_link}": {
        "uri": "https://attiks.com",
        "title": "Print and web design"
      }
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Linktest created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Linktest created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}

## POST /link-test

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
      "{field_linkmulti}": [
        {
          "uri": "https://attiks.com",
          "title": "Print and web design"
        },
        {
          "uri": "https://unocha.org",
          "title": "UNOCHA"
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
  "message": "Linktest created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Linktest created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc3}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /link-test

Get docs.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /link-test/{doc1}

Get docs.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.silk_my_link_field.uri: "https://attiks.com"
* Data.silk_my_link_field.title: "Print and web design"
* Data.silk_my_multi_link_field[1].uri: "https://unocha.org"
* Data.silk_my_multi_link_field[1].title: "UNOCHA"

## DELETE /types/linktestx

Delete test type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
