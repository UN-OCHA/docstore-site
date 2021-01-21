# Create vocabularies

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "City",
  "author": "hid_123456789"
}
```
===

Example output.

```json
{
  "message": "Vocabulary created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Vocabulary created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {city}

## GET /vocabularies/{city}

Get a vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "label": "City"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.label: /./
* Data.machine_name: {city}

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Organization",
  "author": "hid_123456789"
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
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {organization}

## `GET /vocabularies/{organization}`

Get a vocabulary.

* Accept: "application/json"

===

Example output.

```json
{
  "label": "Organization"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.label: /./
* Data.machine_name: {organization}

## POST /vocabularies/{organization}/fields

Add iso3 field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "ISO 3 code",
  "author": "hid_123456789",
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

## POST /vocabularies/{organization}/fields

Add active field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Active",
  "author": "hid_123456789",
  "type": "boolean"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_active}

# Create terms

Create city terms.

## POST /vocabularies/{city}/terms

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Antwerp",
  "author": "23cdf322",
  "vocabulary": "{city}"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {city_antwerp}

## POST /vocabularies/{city}/terms

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Brussels",
  "author": "23cdf322",
  "vocabulary": "{city}"
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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {city_brussels}

## GET /vocabularies/{city}/terms/{city_brussels}

* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "label": "Brussels",
  "vocabulary_name": "silk_city",
  "langcode": "en",
  "status": "1",
  "name": "Brussels",
  "author": "23cdf322"
}
```

Example output.

* Status: `200`
* Content-Type: "application/json"

## POST /vocabularies/{city}/terms

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Borgerhout",
  "author": "23cdf322",
  "description": "Great district in Antwerp",
  "vocabulary": "{city}"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {city_borgerhout}

## GET /vocabularies/{city}/terms/{city_borgerhout}

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "label": "Borgerhout",
  "vocabulary_name": "{city}",
  "langcode": "en",
  "status": "1",
  "name": "Borgerhout"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.label: /./
* Data.vocabulary_name: {city}

## PUT /vocabularies/{city}/terms/{city_borgerhout}

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Borgerhout district"
}
```

===

Example output.

```json
{
  "message": "Term updated"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {city_borgerhout}

## GET /vocabularies/{city}/terms/{city_borgerhout}

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "label": "Borgerhout district",
  "vocabulary_name": "{city}",
  "langcode": "en",
  "status": "1"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.description: null
* Data.vocabulary_name: {city}

## PATCH /vocabularies/{city}/terms/{city_borgerhout}

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "description": "Borgerhout district"
}
```

===

Example output.

```json
{
  "message": "Term updated"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {city_borgerhout}

## DELETE /vocabularies/{city}/terms/{city_brussels}

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "message": "Term deleted"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term deleted"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/

## GET /vocabularies/{city}/terms/{city_brussels}

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

* Status: `404`
* Content-Type: "application/json"

## POST /vocabularies/{organization}/terms

Create organization terms.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "CERF",
  "author": "23cdf322",
  "{field_iso3}": "BEL",
  "vocabulary": "{organization}"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {organization_cerf}

## POST /vocabularies/{organization}/terms

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "UNOCHA",
  "author": "23cdf322",
  "vocabulary": "{organization}"
}
```

===

Example output.

```json
{
  "message": "Term created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {organization_unocha}

## POST /vocabularies/{organization}/terms

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "WFP",
  "author": "23cdf322",
  "metadata": [
    {
      "{field_iso3}": "BEL"
    }
  ],
  "vocabulary": "{organization}"
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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {organization_wfp}

## GET /vocabularies/{organization}/terms/{organization_wfp}

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "label": "WFP",
  "vocabulary_name": "{organization}",
  "langcode": "en",
  "status": "1",
  "name": "WFP",
  "{field_iso3}": "BEL"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.label: /./
* Data.vocabulary_name: {organization}

## PUT /vocabularies/{organization}/terms/{organization_wfp}

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "WFP",
  "description": "Term updated",
  "metadata": [
    {
      "{field_iso3}": "NED"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Term updated"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Term updated"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/

## GET /vocabularies/{organization}/terms/{organization_wfp}

* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "label": "WFP",
  "vocabulary_name": "{organization}",
  "langcode": "en",
  "status": "1",
  "name": "WFP",
  "{field_iso3}": "NED"
}
```

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/
* Data.label: /./
* Data.description: "Term updated"
* Data.vocabulary_name: {organization}

# Add fields to documents

## POST /types/document/fields

Add city field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My hometown",
  "author": "hid_123456789",
  "target": "{city}"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_city}

## POST /types/document/fields

Add organizations field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Organizations",
  "author": "hid_123456789",
  "target": "{organization}",
  "multiple": true
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_organization}

## POST /types/document/fields

Add id field.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My Id",
  "author": "hid_123456789",
  "type": "integer"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_id}

## POST /documents/documents

Add a document without a file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc with term, no files",
  "author": "hid_123456789",
  "metadata": [
    {
      "{field_id}": 42
    },
    {
      "{field_city}": "{city_borgerhout}"
    },
    {
      "{field_organization}": [
        "{organization_wfp}",
        "{organization_unocha}"
      ]
    }
  ]
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

Example output.

```json
[
]
```

* Status: `200`
* Content-Type: "application/json"

## POST /documents/documents

Add a document using term labels.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc with term labels, no files",
  "author": "hid_123456789",
  "metadata": [
    {
      "{field_id}": 42
    },
    {
      "{field_city}_label": "Paris"
    },
    {
      "{field_organization}_label": [
        "WFP",
        "UNHCR"
      ]
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Document created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc2}

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

## GET /documents/documents

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}
* Data[0].title: "Doc with term, no files"
* Data[0].silk_my_id: 42 // Hard-coded field name!

## GET /media

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {media1}

## POST /documents/documents

Add a document with a file.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Doc term and file",
  "author": "hid_123456789",
  "files": [
    "{media1}"
  ],
  "metadata": [
    {
      "{field_organization}": [
        "{organization_unocha}"
      ]
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Document created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc3}

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

## GET /documents/documents/{doc3}

Get document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}
* Data.title: "Doc term and file"
* Data.files[0].media_uuid: "{media1}"
* Data.files[0].private: true
* Data.files[0].uri: /.*/

## GET /documents/documents/{doc3}

Get document as anonymous

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}
* Data.title: "Doc term and file"
* Data.files[0].media_uuid: "{media1}"
* Data.files[0].private: true
* Data.files[0].uri: null

## GET /documents/documents/{doc3}

Get document as other user

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc3}
* Data.title: "Doc term and file"
* Data.files[0].media_uuid: "{media1}"
* Data.files[0].private: true
* Data.files[0].uri: null

## POST /documents/documents

Add an unpublished document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Unpublished doc",
  "author": "hid_123456789",
  "published": false
}
```

===

Example output.

```json
{
  "message": "Document created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc4}

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

## POST /documents/documents

Add a private document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Private doc",
  "author": "hid_123456789",
  "private": true
}
```

===

Example output.

```json
{
  "message": "Document created"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Document created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc5}

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

## GET /documents/documents/{doc4}

Get unpublished document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc4}
* Data.published: false

## GET /documents/documents/{doc5}

Get private document as owner.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data.uuid: {doc5}
* Data.private: true

## GET /documents/documents/{doc4}

Get unpublished document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"
* Data.message: "Document {doc4} does not exist"

## GET /documents/documents/{doc5}

Get private document as anonymous.

* Accept: "application/json"

===

* Status: `404`
* Content-Type: "application/json"
* Data.message: "Document {doc5} does not exist"

## GET /documents/documents/{doc4}

Get unpublished document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"
* Data.message: "Document {doc4} does not exist"

## GET /documents/documents/{doc5}

Get private document as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `404`
* Content-Type: "application/json"
* Data.message: "Document {doc5} does not exist"

## DELETE /vocabularies/{organization}/terms/{organization_wfp}

Delete a term which is in use.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "message": "Term is in use and can not be deleted"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Term is in use and can not be deleted"

## GET /documents/documents

Test filters.

* Accept: "application/json"
* API-KEY: abcd
* ?filter[f1][group][conjunction]=OR
* ?filter[p1][condition][path]={field_id}
* ?filter[p1][condition][value]=42

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents/documents

Test sort.

* Accept: "application/json"
* API-KEY: abcd
* ?sort=-created

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc5}

## GET /documents/documents

Test sort as anonymous.

* Accept: "application/json"
* ?sort=-created

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc3}

## GET /documents/documents

Test limit.

* Accept: "application/json"
* API-KEY: abcd
* ?page[limit]=1

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc1}

## GET /documents/documents

Test offset.

* Accept: "application/json"
* API-KEY: abcd
* ?page[offset]=77

===

```json
[]
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents/documents

Test offset as anonymous.

* Accept: "application/json"
* ?page[offset]=4

===

```json
[]
```

* Status: `200`
* Content-Type: "application/json"

## GET /documents/documents

Test illegal sort.

* Accept: "application/json"
* API-KEY: abcd
* ?sort=-createdxx

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Sort \"createdxx\" is not valid solr field."

## GET /documents/documents

Test full text search.

* Accept: "application/json"
* API-KEY: abcd
* ?s=Paris

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].uuid: {doc2}
