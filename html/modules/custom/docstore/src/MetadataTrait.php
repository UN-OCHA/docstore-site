<?php

namespace Drupal\docstore;

use Drupal\field\Entity\FieldConfig;
use Drupal\docstore\Controller\VocabularyController;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * Build metadata structure.
 */
trait MetadataTrait {

  /**
   * Build item data from metadata.
   *
   * @return array
   *   Item for Entity::create.
   */
  protected function buildItemDataFromMetaData($metadata, $type, $provider, $author, $entity_type) {
    $item = [];

    if (!is_array($metadata) || $this->arrayIsAssociative($metadata)) {
      throw new \Exception('Metadata has to be an array');
    }

    foreach ($metadata as $metaitem) {
      foreach ($metaitem as $key => $values) {
        // Check for label keys.
        if (strpos($key, '_label')) {
          $key = str_replace('_label', '', $key);
          $values = $this->mapOrCreateTerms($key, $values, $type, $provider, $author, $entity_type);
        }

        // Make sure user has access to the field.
        if (!$this->providerCanUseField($key, $type, $entity_type, $provider)) {
          throw new \Exception(strtr('You do not have access to field @field', [
            '@field' => $key,
          ]));
        }

        // Plain values.
        if (!is_array($values)) {
          $item[$key][] = [
            'value' => $values,
          ];
        }
        else {
          if (!isset($item[$key])) {
            $item[$key] = [];
          }

          // Single value with multiple properties or action.
          if ($this->arrayIsAssociative($values)) {
            if (isset($values['_action']) && $values['_action'] === 'lookup') {
              // Lookup target.
              $entity = $this->findTargetByProperty($values['_reference'], $values['_target'], $values['_field'], $values['value']);
              if ($entity) {
                $item[$key][] = [
                  'target_uuid' => $entity->uuid(),
                ];
              }
            }
            elseif (isset($values['_action']) && $values['_action'] === 'create') {
              // Allow on the fly creation of child items.
              if ($values['_reference'] === 'node') {
                // Add author.
                if (!isset($values['_data']['author'])) {
                  $values['_data']['author'] = $author;
                }

                $child_document = $this->createDocumentForProvider($values['_target'], $values['_data'], $provider);
                if ($child_document) {
                  $item[$key][] = [
                    'target_uuid' => $child_document->uuid(),
                  ];
                }
              }

              if ($values['_reference'] === 'term') {
                // Add author.
                if (!isset($values['_data']['author'])) {
                  $values['_data']['author'] = $author;
                }

                $target_vocabulary = $this->loadVocabulary($values['_target']);
                $child_term = $this->createTermFromParameters($values['_data'], $target_vocabulary, $provider);
                if ($child_term) {
                  $item[$key][] = [
                    'target_uuid' => $child_term->uuid(),
                  ];
                }
              }
            }
            else {
              // No action defined, pass as multi property.
              $item[$key][] = $values;
            }
          }
          else {
            // Multiple values as array.
            foreach ($values as $value) {
              // Nested array.
              if (is_array($value)) {
                if (isset($value['_action']) && $value['_action'] === 'lookup') {
                  // Lookup target.
                  $entity = $this->findTargetByProperty($value['_reference'], $value['_target'], $value['_field'], $value['value']);
                  if ($entity) {
                    $item[$key][] = [
                      'target_uuid' => $entity->uuid(),
                    ];
                  }
                }
                elseif (isset($value['_action']) && $value['_action'] === 'create') {
                  // Allow on the fly creation of child items.
                  if ($value['_reference'] === 'node') {
                    $child_document = $this->createDocumentForProvider($value['_target'], $value['_data'], $provider);
                    if ($child_document) {
                      $item[$key][] = [
                        'target_uuid' => $child_document->uuid(),
                      ];
                    }
                  }

                  if ($value['_reference'] === 'term') {
                    // Add author.
                    if (!isset($values['_data']['author'])) {
                      $values['_data']['author'] = $author;
                    }

                    $target_vocabulary = $this->vocabularyLoad($value['_target']);
                    $child_term = $this->createTermFromParameters($value['_data'], $target_vocabulary, $provider);
                    if ($child_term) {
                      $item[$key][] = [
                        'target_uuid' => $child_term->uuid(),
                      ];
                    }
                  }
                }
                else {
                  // No action defined, pass as multi property.
                  $item[$key][] = $value;
                }
              }
              else {
                // Assume it's a uuid.
                $item[$key][] = [
                  'target_uuid' => $value,
                ];
              }
            }
          }
        }
      }
    }

    return $item;
  }

  /**
   * Map or create terms based on field and label.
   */
  protected function mapOrCreateTerms($field_name, $values, $type, $provider, $author, $entity_type) {
    if ($entity_type === 'node') {
      $field = FieldConfig::loadByName('node', $type, $field_name);
    }
    else {
      $field = FieldConfig::loadByName('taxonomy_term', $type, $field_name);
    }

    if (!$field) {
      throw new \Exception(strtr('Field @field does not exist on @type', [
        '@field' => $field_name,
        '@type' => $type,
      ]));
    }

    if ($field->getType() !== 'entity_reference_uuid') {
      throw new \Exception(strtr('Field @field is not a reference field', ['@field' => $field]));
    }

    if ($field->getSetting('target_type') !== 'taxonomy_term') {
      throw new \Exception(strtr('Field @field does not reference a vocabulary', ['@field' => $field]));
    }

    $handler_settings = $field->getSetting('handler_settings');
    $bundles = array_values($handler_settings['target_bundles']);
    $bundle = reset($bundles);

    // Load vocabulary.
    $vocabulary = $this->loadVocabulary($bundle);

    $vocabulary_controller = new VocabularyController(
      $this->config,
      $this->database,
      $this->entityFieldManager,
      $this->entityRepository,
      $this->entityTypeManager,
      $this->loggerFactory,
      $this->state,
      $this->entityUsage,
    );

    // Loop values.
    if (!is_array($values)) {
      $values = [$values];
    }

    foreach ($values as $key => &$value) {
      // Skip empty values.
      if (empty(trim($value))) {
        unset($values[$key]);
        continue;
      }

      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'name' => $value,
        'vid' => $vocabulary->id(),
      ]);

      if ($terms) {
        $term = reset($terms);
        $value = $term->uuid();

        continue;
      }

      // Check if provider can create terms.
      if ($vocabulary->getThirdPartySetting('docstore', 'provider_uuid') !== $provider->uuid()) {
        if (!$vocabulary->getThirdPartySetting('docstore', 'content_allowed', FALSE)) {
          throw new \Exception(strtr('You are not allowed to create new terms in @vocabulary', ['@vocabulary' => $vocabulary->label()]));
        }
      }

      // Create term.
      $params = [
        'label' => $value,
        'author' => $author,
      ];

      $term = $vocabulary_controller->createTermFromParameters($params, $vocabulary, $provider);
      $value = $term->uuid();
    }

    return $values;
  }

  /**
   * Look up target by property.
   */
  protected function findTargetByProperty($reference, $target, $field, $value) {
    $entities = [];

    if ($reference === 'node') {
      $entities = $this->entityTypeManager->getStorage($reference)->loadByProperties([
        $field => $value,
        'type' => $target,
      ]);
    }
    else {
      $entities = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        $field => $value,
        'vid' => $target,
      ]);
    }

    if ($entities) {
      return reset($entities);
    }
  }

  /**
   * Create a term.
   *
   * @param array $params
   *   Array of values.
   * @param \Drupal\taxonomy\Entity\Vocabulary $vocabulary
   *   Vocabulary.
   * @param \Drupal\user\Entity\User $provider
   *   Provider.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   Newly created term.
   */
  public function createTermFromParameters(array $params, Vocabulary $vocabulary, User $provider) {
    if (empty($params['author'])) {
      throw new \Exception('Author is required');
    }

    // Term.
    $item = [
      'name' => $params['label'],
      'vid' => $vocabulary->id(),
      'author' => $params['author'],
      'created' => [],
      'provider_uuid' => [],
      'parent' => [],
      'description' => $params['description'] ?? '',
    ];

    // Set creation time.
    $item['created'][] = [
      'value' => time(),
    ];

    // Set owner.
    $item['provider_uuid'][] = [
      'target_uuid' => $provider->uuid(),
    ];

    // Check for meta tags.
    if (isset($params['metadata']) && $params['metadata']) {
      $metadata = $params['metadata'];
      $item = array_merge($item, $this->buildItemDataFromMetaData($metadata, $vocabulary->id(), $provider, $params['author'], 'term'));
    }

    // Create term.
    $term = Term::create($item);

    // Check for invalid fields.
    foreach ($item as $key => $data) {
      if (!$term->hasField($key)) {
        throw new \Exception(strtr('Unknown field @field', [
          '@field' => $key,
        ]));
      }
    }

    // Save.
    $term->save();

    return $term;
  }

  /**
   * Check if a provider has access to the field.
   *
   * @param string $field_name
   *   Field name.
   * @param string $bundle
   *   Entity bundle (vocabulary or node type).
   * @param string $entity_type
   *   Entity type (term, taxonomy_term or node).
   * @param \Drupal\Core\Session\AccountInterface $provider
   *   Provider.
   *
   * @return bool
   *   Whether the provider can use the field or not.
   *
   * @throw \Exception
   *   Generic execption when the field doesn't exist.
   *
   * @todo review the exception and make that more generic, for example if we
   * want to allow metadata on providers or files.
   *
   * @todo review if this shouldn't be moved to the ProviderTrait instead to
   * go with the other `providerCan` methods.
   */
  protected function providerCanUseField($field_name, $bundle, $entity_type, AccountInterface $provider) {
    switch ($entity_type) {
      case 'term':
      case 'taxonomy_term':
        // Use real entity type.
        $entity_type = 'taxonomy_term';

        $public_fields = [
          'default_langcode',
          'description',
          'langcode',
          'name',
          'revision_default',
          'revision_translation_affected',
          'status',
          'uuid',
        ];
        break;

      case 'node':
        $public_fields = [
          'boost_document',
          'changed',
          'created',
          'langcode',
          'provider',
          'published',
          'rendered_item',
          'search_api_id',
          'search_api_relevance',
          'title',
          'type',
          'uuid',
          'vid',
        ];
        break;
    }

    static $cache = [];

    if (isset($cache[$entity_type][$bundle][$field_name][$provider->id()])) {
      return $cache[$entity_type][$bundle][$field_name][$provider->id()];
    }

    // Public fields are usable by any provider.
    if (in_array($field_name, $public_fields)) {
      $cache[$entity_type][$bundle][$field_name][$provider->id()] = TRUE;
      return TRUE;
    }

    // Check if the field exists and get its configuration.
    $field_config = FieldConfig::loadByName($entity_type, $bundle, $field_name);
    if (!$field_config) {
      // @todo review what type of exception to send.
      throw new \Exception(strtr('Field @field does not exist', [
        '@field' => $field_name,
      ]));
    }

    // Check if the given provider is the provider of the field.
    if (!$provider->isAnonymous() && $field_config->getThirdPartySetting('docstore', 'provider_uuid') === $provider->uuid()) {
      $cache[$entity_type][$bundle][$field_name][$provider->id()] = TRUE;
      return TRUE;
    }

    // Otherwise check if the field is not private, in which case any provider
    // can access the field.
    if (!$field_config->getThirdPartySetting('docstore', 'private', FALSE)) {
      $cache[$entity_type][$bundle][$field_name][$provider->id()] = TRUE;
      return TRUE;
    }

    // Otherwise deny access to the field.
    $cache[$entity_type][$bundle][$field_name][$provider->id()] = FALSE;
    return FALSE;
  }

}
