# Update provider

## GET /me

Get info.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.shared_secret: "verysecret"

## PUT /me

Create document type with same machine name.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "shared_secret": "xyzzy"
}
```

===

* Status: `200`
* Content-Type: "application/json"

## GET /me

Get info.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.shared_secret: "xyzzy"

## PUT /me

Create document type with same machine name.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "shared_secret": "verysecret"
}
```

===

* Status: `200`
* Content-Type: "application/json"
