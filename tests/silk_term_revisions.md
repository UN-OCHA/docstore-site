# Test revisions on terms

## POST /vocabularies

Create term type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "testrev",
  "label": "Test (revisions)",
  "author": "common"
}
```

===

* Status: `201`
* Content-Type: "application/json"

## POST /vocabularies/testrev/terms

Add a minimal term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Version 1",
  "author": "test"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## GET /vocabularies/testrev/terms

Get term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.results[0].uuid: {term1}

## GET /vocabularies/testrev/terms/{term1}

Test single term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.label: "Version 1"

## GET /vocabularies/testrev/terms/{term1}/revisions

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revisions[0].id: /^[0-9]+$/ // Machine_name {term1_rev1}

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev1}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev1}
* Data.label: "Version 1"

## PUT /vocabularies/testrev/terms/{term1}

Update title.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Version 2",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: {term1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## GET /vocabularies/testrev/terms/{term1}

Test single term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.label: "Version 2"

## GET /vocabularies/testrev/terms/{term1}/revisions

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revisions[0].id: /^[0-9]+$/ // Machine_name {term1_rev2}

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev1}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev1}
* Data.label: "Version 1"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev2}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev2}
* Data.label: "Version 2"

## PATCH /vocabularies/testrev

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

## PUT /vocabularies/testrev/terms/{term1}

Update title.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Version 3",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: {term1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## PATCH /vocabularies/testrev/terms/{term1}

Update description.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "description": "Version 3 rocks"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: {term1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## GET /vocabularies/testrev/terms/{term1}

Test single term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.label: "Version 3"

## GET /vocabularies/testrev/terms/{term1}/revisions

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revisions[0].id: {term1_rev2}

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev1}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev1}
* Data.label: "Version 1"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev2}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev2}
* Data.label: "Version 3"

## PUT /vocabularies/testrev/terms/{term1}

Force a new revision.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Version 4",
  "new_revision": true,
  "revision_log": "Force a new revision",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: {term1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## GET /vocabularies/testrev/terms/{term1}

Test single term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.label: "Version 4"

## GET /vocabularies/testrev/terms/{term1}/revisions

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revisions[0].id: /^[0-9]+$/ // Machine_name {term1_rev4}

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev1}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev1}
* Data.label: "Version 1"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev2}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev2}
* Data.label: "Version 3"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev4}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev4}
* Data.label: "Version 4"

## PUT /vocabularies/testrev/terms/{term1}

Force a new revision as draft.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Version 5",
  "new_revision": true,
  "draft": true,
  "revision_log": "Force a new revision as draft",
  "author": "test"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: {term1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## GET /vocabularies/testrev/terms/{term1}

Test single term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.label: "Version 4"

## GET /vocabularies/testrev/terms/{term1}/revisions

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revisions[0].id: /^[0-9]+$/ // Machine_name {term1_rev5}

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev1}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev1}
* Data.label: "Version 1"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev2}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev2}
* Data.label: "Version 3"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev4}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev4}
* Data.label: "Version 4"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev5}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev5}
* Data.label: "Version 5"

## PUT /vocabularies/testrev/terms/{term1}/revisions/{term1_rev5}/publish

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
* Data.message: "Term revision published"
* Data.uuid: {term1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## GET /vocabularies/testrev/terms/{term1}

Test single term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.label: "Version 5"

## GET /vocabularies/testrev/terms/{term1}/revisions

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revisions[0].id: /^[0-9]+$/ // Machine_name {term1_rev6}

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev1}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev1}
* Data.label: "Version 1"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev2}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev2}
* Data.label: "Version 3"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev4}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev4}
* Data.label: "Version 4"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev5}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev5}
* Data.label: "Version 5"

## GET /vocabularies/testrev/terms/{term1}/revisions/{term1_rev6}

Get term revisions.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}
* Data.revision_id: {term1_rev6}
* Data.label: "Version 5"

## DELETE /vocabularies/testrev/terms/{term1}

Delete term.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {term1}

## DELETE /vocabularies/testrev

Delete term type.

* Content-Type: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
