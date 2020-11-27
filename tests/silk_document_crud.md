# Create documents

## POST /documents

Add a document as anonymous.

* Content-Type: "application/json"
* Accept: "application/json"

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
}
```

===

* Status: `403`
* Content-Type: "application/json"

## POST /documents

Add a document without title.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "hid_123456789"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Title is required"

## POST /documents

Add a document without author.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc with term, no files"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Author is required"

## POST /documents

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789"
}
```

===

Example output.

```json
{
  "message": "Document created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## GET /wait

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[title]=Minimal

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[title]=Not

===

```json
[]
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[f1][group][conjunction]=AND
* ?filter[p1][condition][path]=title
* ?filter[p1][condition][value]=Minimal

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[f1][group][conjunction]=AND
* ?filter[p1][condition][path]=title
* ?filter[p1][condition][value]=Not
* ?filter[p1][condition][value]=Not

===

```json
[]
```

* Status: `200`
* Content-Type: "application/json"
