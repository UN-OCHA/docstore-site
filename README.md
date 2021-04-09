# Document store

Swagger documentation can be found at https://un-ocha.github.io/doc-store-api/

Code can be found at https://github.com/UN-OCHA/docstore-site

## Remarks

When creating content you have to specify an `author`, this is a basic text field and the document store only keeps track of it, it's up to the client application to add validation.

## Provider ([Docs](https://un-ocha.github.io/doc-store-api/#/Provider))

A provider is a Drupal user with access to the API using API keys.

There are regular API keys and read-only keys available.

To get/update information you can use the `api/me` endpoint.

```bash
curl -X GET "https://docstore.local.docksal/api/me" -H  "accept: application/json" -H  "API-KEY: abcd" | jq
```

All POST, PUT, PATCH and DELETE operation do require an API key. To be able to get private and unpublished documents you need an API key as well.
## Document types ([Docs](https://un-ocha.github.io/doc-store-api/#/DocumentType/post-types))

The document store supports multiple document types ("Content types"), these can be created using the API using the `api/types` endpoint.

| field | default | required | info |
| ----- | ------- | -------- | ---- |
| machine_name | | Yes | internal name |
| endpoint | | Yes | the api endpoint to use |
| label | | Yes | visible name |
| shared | true | No | Other users can see these documents |
| content_allowed | true | No | Other providers can create new documents |
| fields_allowed | true | No | Other providers can add their fields |
| author | | Yes | The person who created this |
| use_revisions | true | no | Create new revisions by default |
| allow_duplicates | true | No | Allow duplicate titles |

## Document fields ([Docs](https://un-ocha.github.io/doc-store-api/#/Document/post-document-fields))

To add new fields to a document type, you can use the `api/fields/{type}` endpoint.

| field | default | required | info |
| -----  | -------- | -------- | ---- |
| label | | Yes | visible name |
| author | | Yes | The person who created this |
| type | | Yes | The type of the field |
| target | | No | The target when the field is a reference field |
| multiple | false | No | Multi value field |
| required | false | No | Required field |

### Supported field types

| type | info |
| ---- | ---- |
| boolean | true or false |
| string | varchar(255) |
| node_reference | reference to another document |
| term_reference | reference to a term |
| email | email address|
| timestamp | datetime |
| datetime | datetime |
| daterange | datetime with end value |
| integer | integer |
| string_long | long string (blob) |
| geofield | lat/lon coordinates |

## Documents ([Docs](https://un-ocha.github.io/doc-store-api/#/Document/createDocument))

For all read operation there's an endpoint `api/any` which will query all defined document types. If you want to query 1 specific document type or if you want to create, update or delete a document, you'll have to use to specific endpoint.

| field | default | required | info |
| -----  | -------- | -------- | ---- |
| title | | Yes | visible name |
| author | | Yes | The person who created this |
| published | true | No | Is the document published |
| private | false | No | Is the document private |
| files | [] | No | Array of URI or UUID |
| metadata | [] | No | Array of metadata items |

`files` is an array with mixed values. If the value is a string, it's assumed that it's the `uuid` of an existing media item in the document store. If the value is an object containing a property `uri`, that value is use to retrieve the remote file.

`metadata` contains values for fields, the default format is `"field_name": "value"` but other formats are supported as well.

### Create documents in bulk ([Docs](https://un-ocha.github.io/doc-store-api/#/Document/post-type-bulk))

You can create multiple documents at once using the `api/{type}/bulk` endpoint by passing an array of JSON objects.

### Reference terms using their label

For all fields referencing terms, the API allows you to use the `_label` suffix to specify the label instead of the uuid.

### Lookup a reference using a custom field

If you specify an object, instead of a plain value, you can lookup reference data using any property you want. This allows you for instance to find a country term using the ISO3 code or allows you to find a disaster document using the GLIDE-number.

Example

```json
  {
    "_action": "lookup",
    "_reference": "term",
    "_target": "shared_local_coordination_group",
    "_field": "id",
    "value": "value"
  }
```

### Create child item

If you specify an object, instead of a plain value, you can create reference data using any property you want.

Example

```json
  {
    "_action": "create",
    "_reference": "node",
    "_target": "assessment_document",
    "_data": {
      "author": "AR",
      "title": "Title",
      "files": [],
      "metadata": [
        {
          "accessibility": "Publicly Available"
        },
        {
          "instructions": ""
        }
      ]
    }
  }
```

### Create term with child term

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

### Multi property values

Date range have an end date, so needs special treatment.

```json
  {
    "value": "2011-09-01T00:00:00",
    "end_value": "2011-09-02T00:00:00",
  }
```

Geofield needs to be passed as follows.

```json
  {
    "lat": 4.6,
    "lon": 51,
    "value": "POINT (4.6 51)"
  }
```

## Document revisions ([Docs](https://un-ocha.github.io/doc-store-api/#/Document/get-type-entityid-revisions))

By default all content types have revisions enabled. When updating a document you have the following extra properties.

| field | default | required | info |
| -----  | -------- | -------- | ---- |
| new_revision | | No | create a new revision even if revisions are disabled |
| revision_log | "Updated" | No | revision log message |
| draft | false | No | Is the revision published |

To publish a revision you can use `api/{type}/{id}/revisions/{vid}/publish`, the only property you can set is `revision_log`

## Vocabularies ([Docs](https://un-ocha.github.io/doc-store-api/#/Vocabulary/post-vocabularies))

Creating vocabularies can be at `api/vocabularies`

| field | default | required | info |
| ----- | ------- | -------- | ---- |
| machine_name | | Yes | internal name, will be generated if not specified |
| label | | Yes | visible name |
| shared | true | No | Other users can reference this vocabulary |
| content_allowed | true | No | Other providers can create new terms |
| fields_allowed | true | No | Other providers can add their fields |
| author | | Yes | The person who created this |
| use_revisions | true | no | Create new revisions by default |
| allow_duplicates | true | No | Allow duplicate term names |

Creating fields on a vocabulary is the same process as adding fields to a document type, but add `api/vocabularies/{id}/fields`

## Terms ([Docs](https://un-ocha.github.io/doc-store-api/#/Vocabulary/post-vocabularies-terms))

Terms can be created either at `api/terms` or using the vocabulary specific `api/vocabularies/{id}/terms`

Terms have revisions which can be accessed at `api/terms/{id}/revisions/{revision_id}` and also has support for `api/terms/{id}/revisions/{revision_id}/publish`

When updating terms you can use these fields.

| field | default | required | info |
| -----  | -------- | -------- | ---- |
| new_revision | | No | create a new revision even if revisions are disabled |
| revision_log | "Updated" | No | revision log message |
| draft | false | No | Is the revision published |

## Files ([Docs](https://un-ocha.github.io/doc-store-api/#/File/post-files))

Creating files using HTTP request is a bit special, it can be done using a POST request to `api/files`. You can create a file without any content and later use `api/files/{id}/content` to send the content of the file as a binary string. Updating the file contents will automatically create a new revision of the file on disk.

| field | default | required | info |
| ----- | ------- | -------- | ---- |
| filename | | Yes | Name of the file |
| mimetype | '' | No | Mime type of the file, will be detected if not specified |
| alt | '' | No | Alt dewscription of the file |
| private | false | No | Mark the file as being private |
| data | '' | No | Base64 encode file contents |
| use_dropfolder | FALSE | No | Instructs the document store to scan the dropfolder for the file using the specified filename |

For each file created, the document store creates a media entity to keep track of all the file revisions.

### Getting private files

Private files (the content) can be fetch using either an API key on `api/files/{id}/content` or by using the either `media/{media_uuid}/{provider_uuid}/{hash}/{filename}` or `files/{file_uuid}/{provider_uuid}/{hash}/{filename}`. The first one returns the content of the current published revision, the latter returns the content of a specific version.

## Examples and code snippets

### Tests

Can be found at [./tests](./tests) and are executed using silk.

The [run.sh](./tests/run.sh) script can be used for local testing and is used by Travis.

### PHP scripts

Can be found at [./html/modules/custom/docstore/syncs](./html/modules/custom/docstore/syncs)

The files with a `create_` prefix are stand alone PHP files, which uses the API to create content.

The files with a `docstore_` prefix are drupal scripts which needs to be executed on the server using `drush scr`

## Coverage report

### Generate report

Needs an old version of phpcov.

```bash
wget https://phar.phpunit.de/phpcov-6.0.1.phar
fin exec php phpcov-6.0.1.phar merge --html code-coverage-report code-coverage-report-clover/
```

### Alter index.php

This should be injected using the autoloader.

```php
<?php

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Driver\Xdebug;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;
use SebastianBergmann\CodeCoverage\Report\PHP;

$autoloader = require_once 'autoload.php';

$kernel = new DrupalKernel('prod', $autoloader);

$filter = new Filter;
$filter->addDirectoryToWhitelist('/var/www/html/modules/custom/docstore/src');

$coverage = new CodeCoverage(
  (new Xdebug),
  $filter
);

$coverage->start('req1');

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();

$coverage->stop();


(new HtmlReport)->process($coverage, '/var/www/code-coverage-report');
@mkdir("/var/www/code-coverage-report-clover");
$tmpfname = tempnam("/var/www/code-coverage-report-clover", "report");
(new PHP)->process($coverage, $tmpfname . '.cov');

$kernel->terminate($request, $response);
```
