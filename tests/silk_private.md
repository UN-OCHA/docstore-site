# Test private fields and vocabularies

## POST /vocabularies

Create vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Test (private)",
  "machine_name": "privatetest",
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
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {machine_name}

## POST /vocabularies/{machine_name}/fields

Add pubic field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Public field",
  "machine_name": "test_public",
  "author": "public",
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_public}

## POST /vocabularies/{machine_name}/fields

Add private field to vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Private field",
  "machine_name": "test_private",
  "author": "private",
  "private": true,
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_private}

## POST /vocabularies/{machine_name}/fields

Add private field to vocabulary for other provider.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

```json
{
  "label": "Private field 2",
  "machine_name": "test_private_2",
  "author": "private",
  "private": true,
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
* Data.field_name: /^[0-9a-z_]+$/ // Machine_name {field_private2}

## POST /vocabularies/{machine_name}/terms

Create term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Term 1",
  "author": "23cdf322",
  "metadata": [
    {
      "{field_public}": "I'm visible"
    },
    {
      "{field_private}": "I'm not visible"
    },
    {
      "{field_private2}": "Private from other provider"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "You do not have access to field test_private_2"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You do not have access to field test_private_2"

## POST /vocabularies/{machine_name}/terms

Create term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "Term 1",
  "author": "23cdf322",
  "metadata": [
    {
      "{field_public}": "I'm visible"
    },
    {
      "{field_private}": "I'm not visible"
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
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_1}

## POST /vocabularies/{machine_name}/terms

Create term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

```json
{
  "label": "Term 2",
  "author": "23cdf322",
  "metadata": [
    {
      "{field_public}": "I'm visible"
    },
    {
      "{field_private}": "I'm not visible"
    }
  ]
}
```

===

Example output.

```json
{
  "message": "You do not have access to field test_private"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You do not have access to field test_private"

## POST /vocabularies/{machine_name}/terms

Create term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

```json
{
  "label": "Term 2",
  "author": "23cdf322",
  "metadata": [
    {
      "{field_public}": "I can be used"
    },
    {
      "{field_private2}": "Private from other provider"
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
* Content-Type: "application/json"
* Data.message: "Term created"
* Data.uuid: /^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/ // Machine_name {term_2}

## `GET /vocabularies/{machine_name}/terms`

Get terms.

* Accept: "application/json"

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].test_public: "I'm visible"

## `GET /vocabularies/{machine_name}/terms`

Get terms.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].test_public: "I'm visible"
* Data[0].test_private: "I'm not visible"

## `GET /vocabularies/{machine_name}/terms`

Get terms as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].test_public: "I'm visible"

## PATCH /vocabularies/{machine_name}

Make vocabulary private.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "private": true
}
```

===

Example output.

```json
{
  "message": "Vocabulary updated"
}
```

* Status: `200`
* Content-Type: "application/json"

## `GET /vocabularies/{machine_name}/terms`

Get terms.

* Accept: "application/json"

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You do not have access to this vocabulary"

## `GET /vocabularies/{machine_name}/terms`

Get terms.

* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].test_public: "I'm visible"
* Data[0].test_private: "I'm not visible"

## `GET /vocabularies/{machine_name}/terms`

Get terms as other provider.

* Accept: "application/json"
* API-KEY: dcba

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "You do not have access to this vocabulary"

## DELETE /vocabularies/{machine_name}/terms/{term_1}

Delete term.

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

## DELETE /vocabularies/{machine_name}/terms/{term_2}

Delete term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "message": "Term is not owned by you"
}
```

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Term is not owned by you"

## DELETE /vocabularies/{machine_name}/terms/{term_2}

Delete term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===


* Status: `400`
* Content-Type: "application/json"
* Data.message: "The vocabulary is private"

## PATCH /vocabularies/{machine_name}

Make vocabulary public so ther provider can delete term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "shared": true
}
```

===

Example output.

```json
{
  "message": "Vocabulary updated"
}
```

* Status: `200`
* Content-Type: "application/json"

## DELETE /vocabularies/{machine_name}/terms/{term_2}

Delete term.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

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

## DELETE /vocabularies/{machine_name}

Delete vocabulary.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

Example output.

```json
{
  "message": "Vocabulary deleted"
}
```

* Status: `200`
* Content-Type: "application/json"
* Data.message: "Vocabulary deleted"
