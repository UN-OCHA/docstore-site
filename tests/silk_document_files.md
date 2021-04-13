# Document files

## POST /files

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

## POST /files

Create a private file `file_private_2` with content.

Note: the data is "Private file 2" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": true,
  "filename":"doc-file-private-2.txt",
  "mimetype":"text/plain",
  "data": "UHJpdmF0ZSBmaWxlIDIK"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_private_uuid_2}

## POST /files

Create a private file `file_private_3` with content.

Note: the data is "Private file 3" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": true,
  "filename":"doc-file-private-3.txt",
  "mimetype":"text/plain",
  "data": "UHJpdmF0ZSBmaWxlIDMK"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_private_uuid_3}

## POST /files

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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid_1}

## POST /files

Create a private file `file_public_2` with content.

Note: the data is "Public file 2" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"doc-file-public-2.txt",
  "mimetype":"text/plain",
  "data": "UHVibGljIGZpbGUgMgo="
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid_2}

## POST /files

Create a private file `file_public_3` with content.

Note: the data is "Public file 3" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"doc-file-public-3.txt",
  "mimetype":"text/plain",
  "data": "UHVibGljIGZpbGUgMwo="
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid_3}

## POST /files

Create a private file `file_public_4` with content.

Note: the data is "Public file 4" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"doc-file-public-4.txt",
  "mimetype":"text/plain",
  "data": "UHVibGljIGZpbGUgNAo="
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid_4}

## POST /files

Create a private file `file_public_5` with content.

Note: the data is "Public file 5" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"doc-file-public-5.txt",
  "mimetype":"text/plain",
  "data": "UHVibGljIGZpbGUgNQo="
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid_5}

## POST /files

Create a private file `file_public_6` with content.

Note: the data is "Public file 6" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"doc-file-public-6.txt",
  "mimetype":"text/plain",
  "data": "UHVibGljIGZpbGUgNgo="
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid_6}

## POST /types

Create a document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_doc_files",
  "endpoint": "test-document-files",
  "label": "Test document files",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.endpoint: /.+/ // Endpoint {doc_type}

## POST /documents/{doc_type}

Add public document `doc_public_1` with a private file and a public file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc public 1",
  "author": "common",
  "files": [
    "{file_private_uuid_1}",
    "{file_public_uuid_1}"
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_public_uuid_1}

## POST /documents/{doc_type}

Add public document `doc_public_2` with a private file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc public 2",
  "author": "common",
  "files": [
    "{file_private_uuid_2}"
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_public_uuid_2}

## POST /documents/{doc_type}

Add public document `doc_public_3` with 3 public file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc public 3",
  "author": "common",
  "files": [
    "{file_public_uuid_4}",
    "{file_public_uuid_5}",
    "{file_public_uuid_6}"
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_public_uuid_3}

## POST /documents/{doc_type}

Add private document `doc_private_1` with a public and private file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc private 1",
  "author": "common",
  "private": true,
  "files": [
    "{file_public_uuid_2}",
    "{file_private_uuid_3}"
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_private_uuid_1}

## POST /documents/{doc_type}

Add private document `doc_private_2` with a public file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc private 2",
  "author": "common",
  "private": true,
  "files": "{file_public_uuid_3}"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_private_uuid_2}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/{doc_type}

Get documents as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc_public_uuid_3}
* Data.results[0].files[0].uuid: {file_public_uuid_4}
* Data.results[0].files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[0].files[1].uuid: {file_public_uuid_5}
* Data.results[0].files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[0].files[2].uuid: {file_public_uuid_6}
* Data.results[0].files[2].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[1].uuid: {doc_public_uuid_2}
* Data.results[1].files[0].uuid: {file_private_uuid_2}
* Data.results[1].files[0].uri: null
* Data.results[2].uuid: {doc_public_uuid_1}
* Data.results[2].files[0].uuid: {file_private_uuid_1}
* Data.results[2].files[0].uri: null
* Data.results[2].files[1].uuid: {file_public_uuid_1}
* Data.results[2].files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}

Get documents as another provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc_public_uuid_3}
* Data.results[0].files[0].uuid: {file_public_uuid_4}
* Data.results[0].files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[0].files[1].uuid: {file_public_uuid_5}
* Data.results[0].files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[0].files[2].uuid: {file_public_uuid_6}
* Data.results[0].files[2].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[1].uuid: {doc_public_uuid_2}
* Data.results[1].files[0].uuid: {file_private_uuid_2}
* Data.results[1].files[0].uri: null
* Data.results[2].uuid: {doc_public_uuid_1}
* Data.results[2].files[0].uuid: {file_private_uuid_1}
* Data.results[2].files[0].uri: null
* Data.results[2].files[1].uuid: {file_public_uuid_1}
* Data.results[2].files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}

Get documents as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {doc_private_uuid_2}
* Data.results[0].files[0].uuid: {file_public_uuid_3}
* Data.results[0].files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[1].uuid: {doc_private_uuid_1}
* Data.results[1].files[0].uuid: {file_public_uuid_2}
* Data.results[1].files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[1].files[1].uuid: {file_private_uuid_3}
* Data.results[1].files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[2].uuid: {doc_public_uuid_3}
* Data.results[2].files[0].uuid: {file_public_uuid_4}
* Data.results[2].files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[2].files[1].uuid: {file_public_uuid_5}
* Data.results[2].files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[2].files[2].uuid: {file_public_uuid_6}
* Data.results[2].files[2].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[3].uuid: {doc_public_uuid_2}
* Data.results[3].files[0].uuid: {file_private_uuid_2}
* Data.results[3].files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[4].uuid: {doc_public_uuid_1}
* Data.results[4].files[0].uuid: {file_private_uuid_1}
* Data.results[4].files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[4].files[1].uuid: {file_public_uuid_1}
* Data.results[4].files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/files

Get document files as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {file_public_uuid_4}
* Data.results[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[1].uuid: {file_public_uuid_5}
* Data.results[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[2].uuid: {file_public_uuid_6}
* Data.results[2].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[3].uuid: {file_private_uuid_2}
* Data.results[3].uri: null
* Data.results[4].uuid: {file_private_uuid_1}
* Data.results[4].uri: null
* Data.results[5].uuid: {file_public_uuid_1}
* Data.results[5].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/files

Get document files as different provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {file_public_uuid_4}
* Data.results[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[1].uuid: {file_public_uuid_5}
* Data.results[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[2].uuid: {file_public_uuid_6}
* Data.results[2].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[3].uuid: {file_private_uuid_2}
* Data.results[3].uri: null
* Data.results[4].uuid: {file_private_uuid_1}
* Data.results[4].uri: null
* Data.results[5].uuid: {file_public_uuid_1}
* Data.results[5].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/files

Get document files as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {file_public_uuid_3}
* Data.results[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[1].uuid: {file_public_uuid_2}
* Data.results[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[2].uuid: {file_private_uuid_3}
* Data.results[2].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[3].uuid: {file_public_uuid_4}
* Data.results[3].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[4].uuid: {file_public_uuid_5}
* Data.results[4].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[5].uuid: {file_public_uuid_6}
* Data.results[5].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[6].uuid: {file_private_uuid_2}
* Data.results[6].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[7].uuid: {file_private_uuid_1}
* Data.results[7].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[8].uuid: {file_public_uuid_1}
* Data.results[8].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/{doc_public_uuid_1}

Get a public document as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_public_uuid_1}
* Data.files[0].uuid: {file_private_uuid_1}
* Data.files[0].uri: null
* Data.files[1].uuid: {file_public_uuid_1}
* Data.files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/{doc_public_uuid_1}

Get a public document as different provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_public_uuid_1}
* Data.files[0].uuid: {file_private_uuid_1}
* Data.files[0].uri: null
* Data.files[1].uuid: {file_public_uuid_1}
* Data.files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/{doc_public_uuid_1}

Get a public document as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_public_uuid_1}
* Data.files[0].uuid: {file_private_uuid_1}
* Data.files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.files[1].uuid: {file_public_uuid_1}
* Data.files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/{doc_private_uuid_1}

Get a private document as anonymous.

**Note:** This returns a 404 because this comes from solr and as we filter
by "private" then solr returns 0 results and considers that a 404 though maybe
it should be a 403 access denied.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc_type}/{doc_private_uuid_1}

Get a private document as different provider.

**Note:** This returns a 404 because this comes from solr and as we filter
by "private" then solr returns 0 results and considers that a 404 though maybe
it should be a 403 access denied.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"

## GET /documents/{doc_type}/{doc_private_uuid_1}

Get a private document as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_private_uuid_1}
* Data.files[0].uuid: {file_public_uuid_2}
* Data.files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.files[1].uuid: {file_private_uuid_3}
* Data.files[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/{doc_public_uuid_1}/files

Get a public document's files as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {file_private_uuid_1}
* Data.results[0].uri: null
* Data.results[1].uuid: {file_public_uuid_1}
* Data.results[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/{doc_public_uuid_1}/files

Get a public document's files as a different provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {file_private_uuid_1}
* Data.results[0].uri: null
* Data.results[1].uuid: {file_public_uuid_1}
* Data.results[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## GET /documents/{doc_type}/{doc_public_uuid_1}/files

Get a public document's files as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {file_private_uuid_1}
* Data.results[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
* Data.results[1].uuid: {file_public_uuid_1}
* Data.results[1].uri: /.+\/files\/[0-9a-f-]{36}\/.+/
