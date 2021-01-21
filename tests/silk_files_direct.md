# Files

## GET /files/{FILEPRIVATETXT}/{ME_UUID}/{HASH}/test.pdf

Get private file.

* Content-Type: "application/json"
* Accept: "text/plain"

===

```txt
Private txt

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/{FILEPRIVATETXT}/{ME_UUID}/{HASH}/not_really_used.txt

Get private file.

* Content-Type: "application/json"
* Accept: "text/plain"

===

```txt
Private txt

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/file-uuid/{ME_UUID}/{HASH}/test.txt

Get private file, wrong file uuid.

* Content-Type: "application/json"
* Accept: "text/plain"

===

* Status: `400`

## GET /files/{FILEPRIVATETXT}/provider-uuid/{HASH}/test.txt

Get private file, wrong provider uuid.

* Content-Type: "application/json"
* Accept: "text/plain"

===

* Status: `400`

## GET /files/{FILEPRIVATETXT}/{ME_UUID}/hash/test.txt

Get private file, wrong hash.

* Content-Type: "application/json"
* Accept: "text/plain"

===

* Status: `400`

## PATCH /api/v1/me

Update shared secret

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "shared_secret": "AnotherSecret"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Provider updated"

## GET /files/{FILEPRIVATETXT}/{ME_UUID}/{HASH}/not_really_used.txt

Get private file.

* Content-Type: "application/json"
* Accept: "text/plain"

===

* Status: `400`
