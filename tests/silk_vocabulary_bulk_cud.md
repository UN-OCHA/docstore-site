# Create, update, delete terms in bulk

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Bulk CUD terms",
  "machine_name": "voc_bulk_cud",
  "author": "test",
  "allow_duplicates": false
}
```

===

Example output.

```json
{
  "message": "Vocabulary created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {uuid}
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {machine_name}

## POST /vocabularies/{machine_name}/fields

Add iso3 field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "ISO 3 code",
  "author": "test",
  "type": "string"
}
```

===

Example output.

```json
{
  "message": "Field created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Field created"
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_iso3}


## POST /vocabularies/{machine_name}/terms/bulk

Create terms in bulk.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "terms": [
    {
      "_action": "create",
      "label": "Term1",
      "metadata": [
        {
          "{field_iso3}": "AFG"
        }
      ]
    },
    {
      "_action": "create",
      "label": "Term2",
      "metadata": [
        {
          "{field_iso3}": "BEL"
        }
      ]
    },
    {
      "_action": "create",
      "label": "Term3",
      "metadata": [
        {
          "{field_iso3}": "FRA"
        }
      ]
    },
    {
      "_action": "create",
      "label": "Term4",
      "metadata": [
        {
          "{field_iso3}": "OPT"
        }
      ]
    }
  ]
}
```

===

Example output.

```json
[
  {
    "message": "Term created"
  },
  {
    "message": "Term created"
  },
  {
    "message": "Term created"
  },
  {
    "message": "Term created"
  }
]
```

* Status: `200`
* Content-Type: "application/json"
* Data[0].message: "Term created"
* Data[1].message: "Term created"
* Data[2].message: "Term created"
* Data[3].message: "Term created"
* Data[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term1 uuid {term_uuid1}
* Data[1].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term2 uuid {term_uuid2}
* Data[2].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term3 uuid {term_uuid3}
* Data[3].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term4 uuid {term_uuid4}

## POST /vocabularies/{machine_name}/terms/bulk

Try to create new terms with the same label.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "terms": [
    {
      "_action": "create",
      "label": "Term1",
      "metadata": [
        {
          "{field_iso3}": "AFG"
        }
      ]
    },
    {
      "_action": "create",
      "label": "Term2",
      "metadata": [
        {
          "{field_iso3}": "BEL"
        }
      ]
    }
  ]
}
```

===

Example output.

```json
[
  {
    "error": {
      "status": 400,
      "message": "Term with same label already exists"
    }
  },
  {
    "error": {
      "status": 400,
      "message": "Term with same label already exists"
    }
  }
]
```

* Status: `200`
* Content-Type: "application/json"


## POST /vocabularies/{machine_name}/terms/bulk

Update an existing term, delete an existing term, create new term and attempt to
update a non existing term in the same request.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "author": "Author",
  "terms": [
    {
      "_action": "update",
      "uuid": "{term_uuid1}",
      "label": "Term1 with new label",
      "metadata": [
        {
          "{field_iso3}": "AFG"
        }
      ]
    },
    {
      "_action": "delete",
      "uuid": "{term_uuid4}"
    },
    {
      "_action": "create",
      "label": "Term5",
      "metadata": [
        {
          "{field_iso3}": "AFG"
        }
      ]
    },
    {
      "_action": "update",
      "uuid": "aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa",
      "label": "Non existing term",
      "metadata": [
        {
          "{field_iso3}": "AFG"
        }
      ]
    }
  ]
}
```

===

Example output.

```json
[
  {
    "message": "Term updated",
    "uuid": "{term_uuid1}"
  },
  {
    "message": "Term deleted",
    "uuid": "{term_uuid4}"
  },
  {
    "message": "Term created"
  },
  {
    "error": {
      "status": 404,
      "message": "Term does not exist"
    }
  }
]
```

* Status: `200`
* Content-Type: "application/json"
* Data[2].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Term5 uuid {term_uuid5}
