# Document store

## Naming conventions for fields, vocabularies

- `base_`: basic data needed to make it work
- `shared_`: fields, vocabularies used by all providers
- `hrinfo_`: fields, vocabularies for hrinfo
- `reliefweb_`: fields, vocabularies for reliefweb
- `unocha_`: fields, vocabularies for unocha

## Creating vocabularies, adding fields

```php
$label = 'My field';
$field_type = 'string';
$multiple = FALSE;
$provider_prefix = 'rw_'; // Retrieve from provider.
$new_field_name = docstore_create_document_field_for_provider($label, $field_type, $multiple, $provider_prefix);

$label = 'My vocabulary';
$bundle = docstore_create_vocabulary_for_provider($label, $provider_prefix);

$label = 'My vocabulary field';
$new_field_name = docstore_create_vocabulary_field_for_provider($bundle, $label, $field_type, $multiple, $provider_prefix);

$label = 'Reference to term';
docstore_create_document_reference_field_for_provider($label, $bundle, TRUE);
```
