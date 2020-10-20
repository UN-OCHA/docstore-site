# Document store

## `GET /documents`

Gets a list of documents, all parameters are optional.

* Content-Type: "application/json"
* Accept: "application/json"
* `?name=` // Filter by name

Example output.

```json
[
    {
        "name": "Doc1",
        "id": 132456,
        "created": "2020-08-04T22:33:00Z",
        "fileUrl": "/files/test_doc1.pdf"
    }
]
```

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Doc uuid {docuuid}
* Data[0].title: /./
* Data[0].created: /^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/

## `GET /documents/{docuuid}`

Gets a document.

* Content-Type: "application/json"
* Accept: "application/json"

Example output.

```json
{
    "name": "Doc1",
    "id": 132456,
    "created": "2020-08-04T22:33:00",
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.title: /./
* Data.created: /^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/
