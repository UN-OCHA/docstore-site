# Test creating content with provided UUID.

## POST /types

Create a document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_doc_uuid",
  "endpoint": "test-document-uuid",
  "label": "Test document uuid",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.endpoint: /.+/ // Endpoint {doc_type}

## POST /documents/{doc_type}

Add document `doc_1`.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 1",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_uuid_1}

## POST /documents/{doc_type}

Add document `doc_2` providing an existing uuid.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 2",
  "author": "common",
  "uuid": "{doc_uuid_1}"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Document UUID invalid or already in use"

## POST /documents/{doc_type}

Add document `doc_3` providing a non existing uuid.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 3",
  "author": "common",
  "uuid": "a4a423f3-3db3-4f0e-a850-ce3aacb3e521"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: "a4a423f3-3db3-4f0e-a850-ce3aacb3e521"

## POST /vocabularies

Create a vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "test_voc_uuid",
  "label": "Test vocabulary uuid",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {voc_machine_name}

## POST /vocabularies/{voc_machine_name}/terms

Add term `term_1`.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Term 1",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term UUID {term_uuid_1}

## POST /vocabularies/{voc_machine_name}/terms

Add term `term_2` with an existing uuid.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Term 2",
  "author": "common",
  "uuid": "{term_uuid_1}"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Term UUID invalid or already in use"

## POST /vocabularies/{voc_machine_name}/terms

Add term `term_1`.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Term 3",
  "author": "common",
  "uuid": "be3766a1-a8b9-4bb0-8b0d-34ba9f6b8c0a"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: "be3766a1-a8b9-4bb0-8b0d-34ba9f6b8c0a"

## POST /files

Create a file `file_1` with content.

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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_uuid_1}

## POST /files

Create a file `file_2` with content with an existing uuid.

Note: the data is "Public file 1" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"doc-file-public-2.txt",
  "mimetype":"text/plain",
  "data": "UHVibGljIGZpbGUgMQo=",
  "uuid": "{file_uuid_1}"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "File UUID invalid or already in use"

## POST /files

Create a file `file_3` with content with a non existing uuid.

Note: the data is "Public file 1" encoded in base64.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"doc-file-public-3.txt",
  "mimetype":"text/plain",
  "data": "UHVibGljIGZpbGUgMQo=",
  "uuid": "54590be1-3baf-4ab3-92a1-47ccb80f4915"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: "54590be1-3baf-4ab3-92a1-47ccb80f4915"

## GET /files/{file_uuid_1}/content

Get the content of the file `file_1`.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public file 1

```

* Status: `200`

## GET {HOST}/files/{file_uuid_1}/test.txt

Get the content of the file `file_1`.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public file 1

```

* Status: `200`

## POST /documents/{doc_type}

Add document `doc_4` referencing an existing file as uuid.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 4",
  "author": "common",
  "files": [
    "{file_uuid_1}"
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_uuid_4}

## GET /documents/{doc_type}/{doc_uuid_4}

Check the list of files of the document `doc_4`

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_uuid_4}
* Data.files[0].uuid: {file_uuid_1}
* Data.files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## POST /documents/{doc_type}

Add document `doc_5` referencing an existing file as object.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 5",
  "author": "common",
  "files": [
    {
      "uuid": "{file_uuid_1}"
    }
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_uuid_5}

## GET /documents/{doc_type}/{doc_uuid_5}

Check the list of files of the document `doc_5`

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_uuid_5}
* Data.files[0].uuid: {file_uuid_1}
* Data.files[0].uri: /.+\/files\/[0-9a-f-]{36}\/.+/

## POST /documents/{doc_type}

Add document `doc_6` fetching a file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 6",
  "author": "common",
  "files": [
    {
      "uri": "{HOST}/files/{file_uuid_1}/test-fetch-1.txt"
    }
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_uuid_6}

## GET /documents/{doc_type}/{doc_uuid_6}

Check the list of files of the document `doc_6`

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_uuid_6}
* Data.files[0].uri: /.+\/files\/[0-9a-f-]{36}\/test-fetch-1\.txt/
* Data.files[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {file_fetch_uuid_1}

## GET {HOST}/files/{file_fetch_uuid_1}/test-fetch-1.txt

Get the content of the file `file_fetch_1`.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public file 1

```

* Status: `200`

## POST /documents/{doc_type}

Add document `doc_7` fetching a file, specifying the file uuid.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 7",
  "author": "common",
  "files": [
    {
      "uri": "{HOST}/files/{file_uuid_1}/test-fetch-2.txt",
      "uuid": "f5d5b9f7-37a3-4e5b-99d7-ee0e381f692d"
    }
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_uuid_7}

## GET /documents/{doc_type}/{doc_uuid_7}

Check the list of files of the document `doc_7`

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_uuid_7}
* Data.files[0].uri: /.+\/files\/f5d5b9f7-37a3-4e5b-99d7-ee0e381f692d\/test-fetch-2\.txt/
* Data.files[0].uuid: "f5d5b9f7-37a3-4e5b-99d7-ee0e381f692d" // Machine_name {file_fetch_uuid_2}

## GET {HOST}/files/{file_fetch_uuid_2}/test-fetch-2.txt

Get the content of the file `file_fetch_2`.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public file 1

```

* Status: `200`

## POST /documents/{doc_type}

Add document `doc_8` fetching a file, specifying an existing file uuid.

WARNING: This is allowed, but uri will be ignored if uuid already exists.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 8",
  "author": "common",
  "files": [
    {
      "uri": "{HOST}/files/{file_uuid_1}/test-fetch-3.txt",
      "uuid": "{file_fetch_uuid_1}"
    }
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document uuid created"

## POST /documents/{doc_type}

Add document `doc_9` getting a file from the dropfolder

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 9",
  "author": "common",
  "files": [
    {
      "filename": "test_file.txt"
    }
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_uuid_9}

## GET /documents/{doc_type}/{doc_uuid_9}

Check the list of files of the document `doc_9`

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_uuid_9}
* Data.files[0].uri: /.+\/files\/[0-9a-f-]{36}\/test_file\.txt/
* Data.files[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {file_dropfolder_uuid_1}

## GET {HOST}/files/{file_dropfolder_uuid_1}/test_file.txt

Get the content of the file `file_dropfolder_1`.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Test file for drop folder import

```

* Status: `200`

## POST /documents/{doc_type}

Add document `doc_10` fetching a file, specifying the file uuid.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 10",
  "author": "common",
  "files": [
    {
      "filename": "test_file.txt",
      "uuid": "2d315f23-cb71-4381-8e72-6ddda2d7d234"
    }
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc_uuid_10}

## GET /documents/{doc_type}/{doc_uuid_10}

Check the list of files of the document `doc_10`

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc_uuid_10}
* Data.files[0].uri: /.+\/files\/2d315f23-cb71-4381-8e72-6ddda2d7d234\/test_file\.txt/
* Data.files[0].uuid: "2d315f23-cb71-4381-8e72-6ddda2d7d234" // Machine_name {file_dropfolder_uuid_2}

## GET {HOST}/files/{file_dropfolder_uuid_2}/test_file.txt

Get the content of the file `file_fetch_2`.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Test file for drop folder import

```

* Status: `200`

## POST /documents/{doc_type}

Add document `doc_11` fetching a file, specifying an existing file uuid.

WARNING: This is allowed, but uri will be ignored if uuid already exists.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc 11",
  "author": "common",
  "files": [
    {
      "filename": "test_file.txt",
      "uuid": "{file_fetch_uuid_1}"
    }
  ]
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document uuid created"

