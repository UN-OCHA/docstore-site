<?php

namespace Drupal\docstore\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Store location parents.
 *
 * @SearchApiProcessor(
 *   id = "docstore_store_store_parent",
 *   label = @Translation("Store parents"),
 *   description = @Translation("Store parents."),
 *   stages = {
 *     "add_properties" = 20,
 *   },
 *   locked = true,
 *   hidden = false,
 * )
 */
class StoreParents extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    // This check means the field the is not tied to a data source and will
    // appear under "General" in the selectable fields in the UI.
    if (empty($datasource)) {
      $definition = [
        'label' => $this->t('Parents'),
        'description' => $this->t('Stores the parent of a term.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ];
      // Using an underscore at the beginning to avoid clash with custom
      // fields added by the providers.
      $properties['parents'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    /** @var \Drupal\search_api\Item\FieldInterface $storage_field */
    $storage_field = $item->getField('parents', FALSE);

    // Skip if the item doesn't have any storage field.
    if (empty($storage_field)) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $item->getOriginalObject()->getValue();

    if (!$entity->hasField('parent')) {
      return;
    }

    $values = [];
    foreach ($entity->parent->referencedEntities() as $parent) {
      $values = array_merge($values, $this->getParentValues($parent));
    }

    if (!empty($values)) {
      $values = array_unique($values);
      $storage_field->setValues($values);
    }
  }

  /**
   * Get all parents.
   */
  protected function getParentValues($term) {
    $parents = [
      $term->uuid(),
    ];

    if (isset($term->parent)) {
      foreach ($term->parent->referencedEntities() as $parent) {
        $parents = array_merge($parents, $this->getParentValues($parent));
      }
    }

    return $parents;
  }

}
