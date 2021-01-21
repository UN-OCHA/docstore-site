# Test revisions on documents

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "testrev",
  "endpoint": "test-revisions",
  "label": "Test document (revisions)",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"

## POST /documents/test-revisions

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Version 1",
  "author": "test"
}
```

===

Example output.

```json
{
  "message": "Test document (revisions) created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Test document (revisions) created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-revisions

Get document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents/test-revisions/{doc1}

Test single document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.title: "Version 1"

## GET /documents/test-revisions/{doc1}/revisions

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.revisions[0].vid: /^[0-9]+$/ // Machine_name {doc1_rev1}

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev1}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev1}
* Data.title: "Version 1"

## PUT /documents/test-revisions/{doc1}

Update title.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Version 2",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document (revisions) updated"
* Data.uuid: {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-revisions/{doc1}

Test single document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.title: "Version 2"

## GET /documents/test-revisions/{doc1}/revisions

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.revisions[0].vid: /^[0-9]+$/ // Machine_name {doc1_rev2}

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev1}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev1}
* Data.title: "Version 1"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev2}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev2}
* Data.title: "Version 2"

## PATCH /types/testrev

Disable revisions.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "use_revisions": false
}
```

===

* Status: `200`
* Content-Type: "application/json"

## PUT /documents/test-revisions/{doc1}

Update title.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Version 3",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document (revisions) updated"
* Data.uuid: {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-revisions/{doc1}

Test single document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.title: "Version 3"

## GET /documents/test-revisions/{doc1}/revisions

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.revisions[0].vid: {doc1_rev2}

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev1}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev1}
* Data.title: "Version 1"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev2}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev2}
* Data.title: "Version 3"

## PUT /documents/test-revisions/{doc1}

Force a new revision.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Version 4",
  "new_revision": true,
  "revision_log": "Force a new revision",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document (revisions) updated"
* Data.uuid: {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-revisions/{doc1}

Test single document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.title: "Version 4"

## GET /documents/test-revisions/{doc1}/revisions

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.revisions[0].vid: /^[0-9]+$/ // Machine_name {doc1_rev4}

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev1}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev1}
* Data.title: "Version 1"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev2}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev2}
* Data.title: "Version 3"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev4}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev4}
* Data.title: "Version 4"

## PUT /documents/test-revisions/{doc1}

Force a new revision as draft.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Version 5",
  "new_revision": true,
  "draft": true,
  "revision_log": "Force a new revision as draft",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document (revisions) updated"
* Data.uuid: {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-revisions/{doc1}

Test single document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.title: "Version 4"

## GET /documents/test-revisions/{doc1}/revisions

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.revisions[0].vid: /^[0-9]+$/ // Machine_name {doc1_rev5}

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev1}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev1}
* Data.title: "Version 1"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev2}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev2}
* Data.title: "Version 3"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev4}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev4}
* Data.title: "Version 4"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev5}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev5}
* Data.title: "Version 5"

## PUT /documents/test-revisions/{doc1}/revisions/{doc1_rev5}/publish

Publish version 5.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "revision_log": "Make version 5 public"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Test document (revisions) updated"
* Data.uuid: {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents/test-revisions/{doc1}

Test single document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.title: "Version 5"

## GET /documents/test-revisions/{doc1}/revisions

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.revisions[0].vid: /^[0-9]+$/ // Machine_name {doc1_rev6}

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev1}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev1}
* Data.title: "Version 1"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev2}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev2}
* Data.title: "Version 3"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev4}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev4}
* Data.title: "Version 4"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev5}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev5}
* Data.title: "Version 5"

## GET /documents/test-revisions/{doc1}/revisions/{doc1_rev6}

Get document revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}
* Data.vid: {doc1_rev6}
* Data.title: "Version 5"

## DELETE /documents/test-revisions/{doc1}

Delete document.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc1}

## DELETE /types/testrev

Delete document type.

* Content-Type: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
