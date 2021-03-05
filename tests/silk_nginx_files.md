# tests

## POST /api/v1/files

Create a public file `file_public_1` with content.

Note: the data is "Public file 1" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"doc-file-public-1.txt",
  "mimetype":"text/plain",
  "data": "UHVibGljIGZpbGUgMQo="
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid_1}

## POST /api/v1/files

Create a private file `file_private_1` with content.

Note: the data is "Private file 1" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": true,
  "filename":"doc-file-private-1.txt",
  "mimetype":"text/plain",
  "data": "UHJpdmF0ZSBmaWxlIDEK"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_private_uuid_1}

## GET /files/not-a-uuid/pouet.txt

Get a file with a wrong uuid.

===

* Status: `404`

## GET /files/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa/pouet.txt

Get a non-existing file.

===

* Status: `404`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as anonymous

===

```
Public file 1

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as a different provider

* API-KEY: dcba

===

```
Public file 1

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as the owner

* API-KEY: abcd

===

```
Public file 1

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.pdf

Get the content of the public file `file_public_1` as anonymous with the wrong
extension.

===

* Status: `404`

## GET /files/{file_public_uuid_1}/pouet.pdf

Get the content of the public file `file_public_1` as a different provider with
the wrong extension.

===

* Status: `404`

## GET /files/{file_public_uuid_1}/pouet.pdf

Get the content of the public file `file_public_1` as the owner with the wrong
extension.

===

* Status: `404`


## GET /files/{file_private_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as anonymous.

===

* Status: `403`

## GET /files/{file_private_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as a different provider.

* API-KEY: dcba

===

* Status: `403`

## GET /files/{file_private_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as the owner.

* API-KEY: abcd

===

```
Private file 1

```

* Status: `200`

## GET /files/{file_private_uuid_1}/pouet.pdf

Get the content of the public file `file_public_1` as anonymous with the wrong
extension.

===

* Status: `404`

## GET /files/{file_private_uuid_1}/pouet.pdf

Get the content of the public file `file_public_1` as a different provider with
the wrong extension.

* API-KEY: dcba

===

* Status: `404`

## GET /files/{file_private_uuid_1}/pouet.pdf

Get the content of the public file `file_public_1` as the owner with the wrong
extension.

* API-KEY: abcd

===

* Status: `404`
