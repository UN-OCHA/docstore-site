<?php

namespace Drupal\docstore\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;
use Drupal\search_api\Processor\ConfigurablePropertyInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Store fields in solr, not used anymore.
 *
 * @see \Drupal\docstore\Plugin\search_api\processor\StoreDocument
 */
class StoreDocumentProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'fields' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $index = $field->getIndex();
    $configuration = $field->getConfiguration();

    $form['#tree'] = TRUE;

    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Contained fields'),
      '#options' => [],
      '#attributes' => ['class' => ['search-api-checkboxes-list']],
      '#default_value' => $configuration['fields'],
      '#required' => TRUE,
    ];
    $datasource_labels = $this->getDatasourceLabelPrefixes($index);
    $properties = $this->getAvailableProperties($index);
    $field_options = [];
    foreach ($properties as $combined_id => $property) {
      list($datasource_id, $name) = Utility::splitCombinedId($combined_id);

      // Skip general datasource.
      if (!$datasource_id) {
        continue;
      }

      $label = $datasource_labels[$datasource_id] . $property->getLabel();
      $field_options[$combined_id] = Utility::escapeHtml($label);
      if ($property instanceof ConfigurablePropertyInterface) {
        $description = $property->getFieldDescription($field);
      }
      else {
        $description = $property->getDescription();
      }
      $form['fields'][$combined_id] = [
        '#attributes' => ['title' => $this->t('Machine name: @name', ['@name' => $name])],
        '#description' => $description,
      ];
    }
    // Set the field options in a way that sorts them first by whether they are
    // selected (to quickly see which one are included) and second by their
    // labels.
    asort($field_options, SORT_NATURAL);
    $selected = array_flip($configuration['fields']);
    $form['fields']['#options'] = array_intersect_key($field_options, $selected);
    $form['fields']['#options'] += array_diff_key($field_options, $selected);

    // Make sure we do not remove nested fields (which can be added via config
    // but won't be present in the UI).
    $missing_properties = array_diff($configuration['fields'], array_keys($properties));
    if ($missing_properties) {
      foreach ($missing_properties as $combined_id) {
        list(, $property_path) = Utility::splitCombinedId($combined_id);
        if (strpos($property_path, ':')) {
          $form['fields'][$combined_id] = [
            '#type' => 'value',
            '#value' => $combined_id,
          ];
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state) {
    $values = [
      'fields' => array_keys(array_filter($form_state->getValue('fields'))),
    ];
    $field->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDescription(FieldInterface $field) {
    $index = $field->getIndex();
    $available_properties = $this->getAvailableProperties($index);
    $datasource_label_prefixes = $this->getDatasourceLabelPrefixes($index);
    $configuration = $field->getConfiguration();

    $fields = [];
    foreach ($configuration['fields'] as $combined_id) {
      list($datasource_id, $property_path) = Utility::splitCombinedId($combined_id);
      $label = $property_path;
      if (isset($available_properties[$combined_id])) {
        $label = $available_properties[$combined_id]->getLabel();
      }
      $fields[] = $datasource_label_prefixes[$datasource_id] . $label;
    }

    return $this->t('Store the following fields: @fields.', [
      '@fields' => implode(', ', $fields),
    ]);
  }

  /**
   * Retrieves label prefixes for an index's datasources.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return string[]
   *   An associative array mapping datasource IDs (and an empty string for
   *   datasource-independent properties) to their label prefixes.
   */
  protected function getDatasourceLabelPrefixes(IndexInterface $index) {
    $prefixes = [
      NULL => $this->t('General') . ' » ',
    ];

    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      $prefixes[$datasource_id] = $datasource->label() . ' » ';
    }

    return $prefixes;
  }

  /**
   * Retrieve all properties available on the index.
   *
   * The properties will be keyed by combined ID, which is a combination of the
   * datasource ID and the property path. This is used internally in this class
   * to easily identify any property on the index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   All the properties available on the index, keyed by combined ID.
   *
   * @see \Drupal\search_api\Utility::createCombinedId()
   */
  protected function getAvailableProperties(IndexInterface $index) {
    $properties = [];

    $datasource_ids = $index->getDatasourceIds();
    $datasource_ids[] = NULL;
    foreach ($datasource_ids as $datasource_id) {
      foreach ($index->getPropertyDefinitions($datasource_id) as $property_path => $property) {
        $properties[Utility::createCombinedId($datasource_id, $property_path)] = $property;
      }
    }

    return $properties;
  }

}
