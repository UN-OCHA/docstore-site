# Files

## GET /files/{FILE_UUID}/{ME_UUID}/{FILE_HASH}/direct.txt

Get private file with real file name.

* Content-Type: "application/json"
* Accept: "text/plain"

===

```txt
Direct txt
```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/{FILE_UUID}/{ME_UUID}/{FILE_HASH}/not_really_used.txt

Get private file with a different filename.

* Content-Type: "application/json"
* Accept: "text/plain"

===

```txt
Direct txt
```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/file-uuid/{ME_UUID}/{FILE_HASH}/direct.txt

Get private file, wrong file uuid.

* Content-Type: "application/json"
* Accept: "text/plain"

===

* Status: `404`

## GET /files/{FILE_UUID}/provider-uuid/{FILE_HASH}/direct.txt

Get private file, wrong provider uuid.

* Content-Type: "application/json"
* Accept: "text/plain"

===

* Status: `404`

## GET /files/{FILE_UUID}/{ME_UUID}/hash/direct.txt

Get private file, wrong hash.

* Content-Type: "application/json"
* Accept: "text/plain"

===

* Status: `400`

## PATCH /api/v1/me

Update shared secret.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "shared_secret": "AnotherVeryVerySecret"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Provider updated"

## GET /files/{FILE_UUID}/{ME_UUID}/{FILE_HASH}/direct.txt

Check that links become invalid after changing the secret.

* Content-Type: "application/json"
* Accept: "text/plain"

===

* Status: `400`
