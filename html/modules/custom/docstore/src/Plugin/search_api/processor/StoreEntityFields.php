<?php

namespace Drupal\docstore\Plugin\search_api\processor;

use Drupal\docstore\ResourceTrait;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Store the pre-processed fields of an entity.
 *
 * @SearchApiProcessor(
 *   id = "docstore_store_entity_fields",
 *   label = @Translation("Store fields of an enitty"),
 *   description = @Translation("Store structured fields."),
 *   stages = {
 *     "add_properties" = 20,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class StoreEntityFields extends ProcessorPluginBase {

  use ResourceTrait;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state store.
   *
   * @var \Drupal\entity_usage\EntityUsage
   */
  protected $entityUsage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Inject the services needed by the ResourceTrait.
    //
    // We cannot easily inject our services by adding parameters to the
    // constructor because of the way the search API plugins we extend, add
    // services in their ::create(). So we add them here.
    //
    // @see Drupal\search_api\Processor\ProcessorPluginBase::create()
    $processor->entityFieldManager = $container->get('entity_field.manager');
    $processor->entityRepository = $container->get('entity.repository');
    $processor->entityTypeManager = $container->get('entity_type.manager');
    $processor->entityUsage = $container->get('entity_usage.usage');

    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    // This check means the field the is not tied to a data source and will
    // appear under "General" in the selectable fields in the UI.
    if (empty($datasource)) {
      $definition = [
        'label' => $this->t('Stored entity fields'),
        'description' => $this->t('Stores the pre-processed fields of an entity.'),
        'type' => 'solr_string_storage',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ];
      // Using an underscore at the beginning to avoid clash with custom
      // fields added by the providers.
      $properties['_stored_entity_fields'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    /** @var \Drupal\search_api\Item\FieldInterface $storage_field */
    $storage_field = $item->getField('_stored_entity_fields', FALSE);

    // Skip if the item doesn't have any storage field.
    if (empty($storage_field)) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $item->getOriginalObject()->getValue();

    // Prepare the field for storage.
    $data = $this->prepareEntityResourceDataForStorage($entity);

    // Set the raw serialized data as value for the field.
    //
    // Note: we use `setValues()` rather than `addValue()` to reset any
    // previously set data and also to avoid passing the data through the
    // the string data type plugin to prevent any unforseen modifications to
    // the data by other processors that affect the string data type.
    $storage_field->setValues([$data]);
  }

}
