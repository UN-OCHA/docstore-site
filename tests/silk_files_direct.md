# Direct file access.

## GET /api/v1/me

Get the provider uuid for `provider_1`.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}$/ // UUID {provider_uuid_1}

## GET /api/v1/me

Get the provider uuid for `provider_2`.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}$/ // UUID {provider_uuid_2}

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
* Data.uuid: /^[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}$/ // UUID {file_public_uuid_1}

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
* Data.uuid: /^[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}$/ // UUID {file_private_uuid_1}

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

* X-Docstore-Provider-Uuid: {provider_uuid_2}

===

```
Public file 1

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as the owner

* X-Docstore-Provider-Uuid: {provider_uuid_1}

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

* X-Docstore-Provider-Uuid: {provider_uuid_2}

===

* Status: `404`

## GET /files/{file_public_uuid_1}/pouet.pdf

Get the content of the public file `file_public_1` as the owner with the wrong
extension.

* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

* Status: `404`


## GET /files/{file_private_uuid_1}/pouet.txt

Get the content of the private file `file_private_1` as anonymous.

===

* Status: `403`

## GET /files/{file_private_uuid_1}/pouet.txt

Get the content of the private file `file_private_1` as a different provider.

* X-Docstore-Provider-Uuid: {provider_uuid_2}
* API-KEY: dcba

===

* Status: `403`

## GET /files/{file_private_uuid_1}/pouet.txt

Get the content of the private file `file_private_1` as the owner.

* X-Docstore-Provider-Uuid: {provider_uuid_1}
* API-KEY: abcd

===

```
Private file 1

```

* Status: `200`

## GET /files/{file_private_uuid_1}/pouet.pdf

Get the content of the private file `file_private_1` as anonymous with the wrong
extension.

===

* Status: `404`

## GET /files/{file_private_uuid_1}/pouet.pdf

Get the content of the private file `file_private_1` as a different provider with
the wrong extension.

* X-Docstore-Provider-Uuid: {provider_uuid_2}
* API-KEY: dcba

===

* Status: `404`

## GET /files/{file_private_uuid_1}/pouet.pdf

Get the content of the private file `file_private_1` as the owner with the wrong
extension.

* X-Docstore-Provider-Uuid: {provider_uuid_1}
* API-KEY: abcd

===

* Status: `404`

## GET /api/v1/files/{file_public_uuid_1}/revisions

Get the revisions for the file.

* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].id: /.+/ // Id of the latest revision {file_public_revision_1}

## PUT /api/v1/files/{file_public_uuid_1}/select

Select the current version of the public file `file_public_1` for the second
provider.

* API-KEY: dcba

```json
{
  "target": "{file_public_revision_1}"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File version selected"


## POST /api/v1/files/{file_public_uuid_1}/content

Updat the content of the public file `file_public_1`.

Note: the data is "Public file 1 - Updated" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```txt
Public file 1 - Updated

```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File content created"
* Data.uuid: {file_public_uuid_1}

## GET /api/v1/files/{file_public_uuid_1}/content

Get the content of the public file `file_public_1` as anomymous

===

```txt
Public file 1 - Updated

```

* Status: `200`

## GET /api/v1/files/{file_public_uuid_1}/content

Get the content of the public file `file_public_1` as another provider

* API-KEY: dcba

===

```txt
Public file 1 - Updated

```

* Status: `200`

## GET /api/v1/files/{file_public_uuid_1}/content

Get the content of the public file `file_public_1` as the owner

* API-KEY: abcd

===

```txt
Public file 1 - Updated

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as anonymous.

It should be the latest version.

===

```txt
Public file 1 - Updated

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as another provider.

It should be the first version as it was selected for this provider.

* X-Docstore-Provider-Uuid: {provider_uuid_2}

===

```txt
Public file 1

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as the owner.

It should be the latest version.

* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

```txt
Public file 1 - Updated

```

* Status: `200`

## PUT /api/v1/files/{file_public_uuid_1}/select

Select the latest version of the public file `file_public_1` for the second
provider.

* API-KEY: dcba

```json
{
  "target": "latest"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File version selected"

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as anonymous.

It should be the latest version.

===

```txt
Public file 1 - Updated

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as another provider.

It should be the latest version.

* X-Docstore-Provider-Uuid: {provider_uuid_2}

===

```txt
Public file 1 - Updated

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as the owner.

It should be the latest version.

* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

```txt
Public file 1 - Updated

```

* Status: `200`

## PUT /api/v1/files/{file_public_uuid_1}/select

Hide the public file `file_public_1` for the second provider.

* API-KEY: dcba

```json
{
  "target": "hidden"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File version selected"

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as anonymous.

It should be the latest version.

===

```txt
Public file 1 - Updated

```

* Status: `200`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as another provider.

It should be a 404 not found.

* X-Docstore-Provider-Uuid: {provider_uuid_2}

===

* Status: `404`

## GET /files/{file_public_uuid_1}/pouet.txt

Get the content of the public file `file_public_1` as the owner.

It should be the latest version.

* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

```txt
Public file 1 - Updated

```

* Status: `200`

