# Create documents

## POST /types

Create document type.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "machine_name": "testdocs",
  "endpoint": "testdocs",
  "label": "Test document type",
  "shared": true,
  "content_allowed": true,
  "fields_allowed": true,
  "author": "common",
  "allow_duplicates": true
}
```

===

* Status: `201`
* Content-Type: "application/json"

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Parent",
  "machine_name": "voc_parent",
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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {uuid}
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {voc_parent}

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Child",
  "machine_name": "voc_child",
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
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // UUID {uuid}
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {voc_child}

## POST /fields/testdocs

Add term ref to parent voc.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Parent term",
  "machine_name": "field_parent",
  "author": "common",
  "type": "term_reference",
  "target": "voc_parent"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_node_parent}

## POST /vocabularies/{voc_parent}/fields

Add child field to parent voc.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Childs",
  "machine_name": "field_childs",
  "author": "hid_123456789",
  "type": "term_reference",
  "target": "voc_child"
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_parent_child}

## POST /testdocs

Add a minimal document.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "metadata": [
    {
      "field_parent_label": "Parent 0"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Testdocs created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Testdocs created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## POST /testdocs

Create term using label.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "title": "Minimal",
  "author": "hid_123456789",
  "metadata": [
    {
      "field_parent_label": "Parent 1"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "Testdocs created"
}
```

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Testdocs created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {doc1}

## POST /vocabularies/voc_parent/terms

Create term using endpoint.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Parent 2",
  "author": "23cdf322",
  "metadata": []
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

## POST /vocabularies/voc_parent/terms

Create term using endpoint with child.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Parent 3",
  "author": "23cdf322",
  "metadata": [
    {"field_childs_label": "Child 1"}
  ]
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

## POST /vocabularies/voc_parent/terms

Create term using endpoint with child.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Parent 4",
  "author": "23cdf322",
  "metadata": [
    {
      "field_childs":   {
        "_action": "create",
        "_reference": "term",
        "_target": "voc_child",
        "_data": {
          "author": "Test",
          "label": "Child 2"
        }
      }
    }
  ]
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
