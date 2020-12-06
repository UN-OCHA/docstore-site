# Files

## GET /files

Get files.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{FILEPRIVATE}

Get private file as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{FILEPRIVATE}

Get private file as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## GET /files/{FILEPUBLIC}

Get public file as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{FILEPUBLIC}

Get public file as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"

## GET /media

Get media.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[2].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Private {media_private}
* Data[3].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Public {media_public}

## GET /media/{media_private}

Get private media as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /media/{media_private}

Get private media as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Media is not owned by you"

## GET /media/{media_public}

Get public media as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.revisions[0]: /^[0-9]*$/ // Public {current_revision}
* Data.revisions[1]: /^[0-9]*$/ // Public {previous_revision}

## GET /media/{media_public}/revisions/{current_revision}

Get public media as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /media/{media_public}/revisions/{previous_revision}

Get public media as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /media/{media_public}

Get public media as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"

## GET /media/{media_private}/content

Get private media content as owner.

* Content-Type: "application/json"
* Accept: "text/plain"
* API-KEY: abcd

===

```txt
Private txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /media/{media_private}/content

Get private media content as anonymous.

* Content-Type: "application/json"

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Media is not owned by you"

## GET /media/{media_public}/content

Get public media content as owner.

* Content-Type: "application/json"
* API-KEY: abcd

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /media/{media_public}/content

Get public media content as anonymous.

* Content-Type: "application/json"

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /media/{media_public}/revisions/{current_revision}/content

Get public media as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

```txt
Public txt - Updated

```

* Status: `200`
* Content-Type: "text/plain;charset=UTF-8"

## GET /media/{media_public}/revisions/{previous_revision}/content

Get public media as owner.

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
* Data.message: "File not found in dropfolder"

## POST /files

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

## PUT /files/{FILEPUBLIC}

Make public file private as anonymous.

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

## PUT /files/{FILEPUBLIC}

Make public file private as other provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

```json
{
  "private": true
}
```

===

* Status: `400`
* Content-Type: "application/json"

## PUT /files/{FILEPUBLIC}

Make public file private as owner.

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

## GET /files/{FILEPUBLIC}

Get now private file as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /files/{FILEPUBLIC}

Get now private file as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "File is not owned by you"

## PUT /files/{FILEPUBLIC}

Update file name.

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

## PUT /files/{FILEPRIVATE}

Update file name.

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

## GET /files/{FILEPUBLIC}

Get public as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.filename: "newname.pdf"

## GET /files/{FILEPRIVATE}

Get private file as owner.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.filename: "newname.pdf"
