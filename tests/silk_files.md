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
