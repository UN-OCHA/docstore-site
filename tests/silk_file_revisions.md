# Files

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

Create a private file `file_public_1` with content.

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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid}
* Data.revision_id: /.+/ // New revision id {file_public_revision_1}

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check the content of the `file_public` as the provider.

* Accept: "text/plain"
* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

```txt
Public file 1

```

* Status: `200`
* Content-Type: /^text\/plain/

## POST /api/v1/files/{file_public_uuid}/content

Update the file content of the public file `file_public` resource.

**Note:** this creates a new revision with the new content.

* Accept: "application/json"
* API-KEY: abcd
* [content](files/public.txt)

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File content created"
* Data.uuid: {file_public_uuid}
* Data.revision_id: /.+/ // New revision id {file_public_revision_2}

## POST /api/v1/files/{file_public_uuid}/content

Update the file content of the public file `file_public` resource.

**Note:** this creates a new revision with the new content.

* Accept: "application/json"
* API-KEY: abcd
* [content](files/public_updated.txt)

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File content created"
* Data.uuid: {file_public_uuid}
* Data.revision_id: /.+/ // New revision id {file_public_revision_3}

## GET /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_1}/content

Check the content of the first revison.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public file 1

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_2}/content

Check the content of the second revison.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public txt

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_3}/content

Check the content of the third revison.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /api/v1/files/{file_public_uuid}/content

Check that the content of the resource is the latest one.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: /^text\/plain/

## POST /api/v1/types

Create a document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_file_revisions",
  "endpoint": "test-file-revisions",
  "label": "Test file revisions",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.endpoint: /.+/ // Endpoint {doc_type}

## POST /api/v1/documents/{doc_type}

Add public document `doc_public_1` with a public file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc public 1",
  "author": "common",
  "files": [
    "{file_public_uuid}"
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_public_uuid_1}

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check that the content of the resource is the latest one for the provider.

* Accept: "text/plain"
* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: /^text\/plain/

## PUT /api/v1/files/{file_public_uuid}/select

Select the first revision for the provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "target": "{file_public_revision_1}"
}
```

===

* Status: `200`
* Data.message: "File version selected"

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check that the content is the first revision one for the provider.

* Accept: "text/plain"
* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

```txt
Public file 1

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check that the content of the resource is the latest one for another provider.

* Accept: "text/plain"
* X-Docstore-Provider-Uuid: {provider_uuid_2}

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_1}/content

Select the content of the  first revision.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public file 1

```

* Status: `200`
* Content-Type: /^text\/plain/

## DELETE /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_1}

Try to delete the first revision for another provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Data.message: "The revision is in use by another provider"

## DELETE /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_1}

Try to delete the first revision for the provider.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Data.message: "Revision deleted"
* Data.uuid: {file_public_uuid}
* Data.revision_id: {file_public_revision_1}

## GET /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_1}

Check that the revision doesn't exist anymore

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `404`

## GET /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_1}/content

Check that the revision content doesn't exist anymore.

* Accept: "text/plain"
* API-KEY: abcd

===

* Status: `404`

## GET /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_2}/content

Check the content of the second revison.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public txt

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_3}/content

Check the content of the second revison.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check that the content of the resource is the latest one for the provider.

**Note:** after deletion of a revision selected for the provider making the
deletion request, the selection is changed to the latest version.

* Accept: "text/plain"
* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check that the content of the resource is the latest one for another provider.

* Accept: "text/plain"
* X-Docstore-Provider-Uuid: {provider_uuid_2}

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: /^text\/plain/

## DELETE /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_3}

Try to delete the latest revision for another provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Data.message: "The revision is in use by another provider"

## DELETE /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_3}

Try to delete the latest revision for the provider.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Data.message: "Revision deleted"
* Data.uuid: {file_public_uuid}
* Data.revision_id: {file_public_revision_3}

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check that the content is the second revision one for the provider.

* Accept: "text/plain"
* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

```txt
Public txt

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check that the content is the second revision one for another provider.

* Accept: "text/plain"
* X-Docstore-Provider-Uuid: {provider_uuid_2}

===

```txt
Public txt

```

* Status: `200`
* Content-Type: /^text\/plain/

## GET /api/v1/files/{file_public_uuid}

Check that the file revision is the second one.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Data.revision_id: {file_public_revision_2}

## DELETE /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_2}

Try to delete the second revision for another provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Data.message: "The revision is in use by another provider"

## DELETE /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_2}

Try to delete the second (and last remaining) revision for the provider.

**Note:** This fails because there is a document referencing the file.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `400`
* Data.message: "File is still in use in 1 places"

## GET /api/v1/files/{file_public_uuid}/usage

Confirm that the file is used in one place.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Data[0]: "/api/v1/documents/{doc_type}/{doc_public_uuid_1}"
* Data[1]: null

## DELETE /api/v1/documents/{doc_type}/{doc_public_uuid_1}

Delete the document referencing the file.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`

## GET /api/v1/files/{file_public_uuid}/usage

Confirm that the file is not used anymore

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Data[0]: null

## DELETE /api/v1/files/{file_public_uuid}/revisions/{file_public_revision_2}

Try to delete the second revision for the provider.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Data.message: "File deleted"

## GET /api/v1/files/{file_public_uuid}

Check that the file doesn't exist anymore.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `404`

## GET /files/{file_public_uuid}/doc-file-public-1.txt

Check that the file doesn't exist anymore.

* Accept: "application/json"
* X-Docstore-Provider-Uuid: {provider_uuid_1}

===

* Status: `404`

