# Files

## POST /files

Create a private file `file_private` with content.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": true,
  "filename":"private.pdf",
  "mimetype":"application/pdf",
  "data": "{FILE_PRIVATE}"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_private_uuid}

## POST /files

Create a public file `file_public` with no content.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename":"public.txt",
  "mime":"text/plain"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {file_public_uuid}

## POST /files/{file_public_uuid}/content

Add the file content to the public file `file_public` resource.

**Note:** the file uuid is the same as above because the file resource didn't
have any content so this is the first revision with the same uuid.

* Accept: "application/json"
* API-KEY: abcd
* [content](files/public.txt)

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File content created"
* Data.uuid: {file_public_uuid}

## GET /files/{file_public_uuid}

Get the public file `file_public` uri.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {file_public_uuid}
* Data.uri: /.+/ // File uri {file_public_uri}
* Data.revision_id: /.+/ // Revision id {file_public_revision_1}

## GET /files/{file_public_uuid}/content

Get the content of the  public file `file_public`.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public txt

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## POST /files/{file_public_uuid}/content

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
* Data.revision_id: /.+/ // New revision id {file_public_revision_2}

## GET /files/{file_public_uuid}

Check that the uri of the resource has not been modified.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {file_public_uuid}
* Data.uri: {file_public_uri}
* Data.revision_id: {file_public_revision_2}

## GET /files/{file_public_uuid}/content

Check that the content of the resource is the latest one.

* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/{file_public_uuid}/revisions/{file_public_revision_1}/content

Check that the first revision still has the old content.

* Accept: "text/plain"
* API-KEY: dcba

===

```txt
Public txt

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/{file_public_uuid}/revisions/{file_public_revision_2}/content

Check that the new revision has the new content.

* Accept: "text/plain"
* API-KEY: dcba

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files

Get files.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {file_private_uuid}
* Data[1].uuid: {file_public_uuid}

## GET /files/{file_private_uuid}

Get private file `file_private` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{file_private_uuid}

Get private file `file_private` as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## GET /files/{file_private_uuid}

Get private file `file_private` as a different provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## GET /files/{file_public_uuid}

Get public file `file_public` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{file_public_uuid}

Get public file `file_public` as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"

## GET /files

Get file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {file_private_uuid}
* Data[1].uuid: {file_public_uuid}

## GET /files/{file_private_uuid}

Get private file of `file_private` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{file_private_uuid}

Get private file of `file_private` as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## GET /files/{file_private_uuid}

Get private file of `file_private` as a different provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## GET /files/{file_public_uuid}

Get public file of `file_public` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.revisions[0].id: /^[0-9]*$/ // Public {current_revision_id}
* Data.revisions[1].id: /^[0-9]*$/ // Public {previous_revision_id}

## GET /files/{file_public_uuid}/revisions/{current_revision_id}

Get current revision of public file of `file_public` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{file_public_uuid}/revisions/{previous_revision_id}

Get previous revision of public file of `file_public` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{file_public_uuid}

Get public file of `file_public` as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{file_private_uuid}/content

Get private file content of `file_private` as owner.

* Content-Type: "application/json"
* Accept: "text/plain"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/pdf"

## GET /files/{file_private_uuid}/content

Get private file content of `file_private` as anonymous.

* Content-Type: "application/json"

===

* Status: `403`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## GET /files/{file_private_uuid}/content

Get private file content of `file_private` as a different provider.

* Content-Type: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## GET /files/{file_public_uuid}/content

Get public file content of `file_public` as owner.

* Content-Type: "application/json"
* API-KEY: abcd

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/{file_public_uuid}/content

Get public file content of `file_public` as anonymous.

* Content-Type: "application/json"

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/{file_public_uuid}/revisions/{current_revision_id}/content

Get file current revision's content of `file_public` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /files/{file_public_uuid}/revisions/{previous_revision_id}/content

Get file previous revision's content of `file_public` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

```txt
Public txt

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## POST /files

Create a file from the dropfolder.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename": "test_file.txt",
  "use_dropfolder": true
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "File created"

## POST /files

Attempt to create a file from the dropfolder, that doesn't exist.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": false,
  "filename": "unknown.txt",
  "use_dropfolder": true
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "File not found in dropfolder: unknown.txt"

## POST /files

Attempt to create a file from the dropfolder for a provider for which the
dropfolder is not enabled.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

```json
{
  "private": false,
  "filename": "test_file.txt",
  "use_dropfolder": true
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Dropfolder is not enabled"

## PUT /files/{file_public_uuid}

Make public file `file_public` private as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "private": true
}
```

===

* Status: `403`
* Content-Type: "application/json"

## PUT /files/{file_public_uuid}

Make public file `file_public` private as other provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

```json
{
  "private": true
}
```

===

* Status: `403`
* Content-Type: "application/json"

## PUT /files/{file_public_uuid}

Make public file `file_public` private as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": true
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File updated"

## GET /files/{file_public_uuid}

Get now private file `file_public` as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{file_public_uuid}

Get now private file `file_public` as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `403`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## GET /files/{file_public_uuid}

Get now private file `file_public` with a different provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

* Status: `403`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"


## PUT /files/{file_private_uuid}

Update private file `file_private` name.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "filename": "newname.pdf"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "File updated"

## GET /files/{file_private_uuid}

Verify the private file `file_private` has the new name.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.filename: "newname.pdf"
