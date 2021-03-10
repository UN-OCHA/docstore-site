<?php

namespace Drupal\docstore\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'selected_file_version' field type.
 *
 * @FieldType(
 *   id = "selected_file_version",
 *   label = @Translation("Selected file version"),
 *   description = @Translation("A field to store a provider uuid and its selected file version."),
 *   category = @Translation("Docstore"),
 *   default_widget = "selected_file_version",
 *   default_formatter = "selected_file_version"
 * )
 */
class SelectedFileVersionType extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'provider_uuid' => [
          'type' => 'varchar',
          'length' => 36,
        ],
        'target' => [
          'type' => 'varchar',
          'length' => 36,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['provider_uuid'] = DataDefinition::create('string')
      ->setLabel(t('Provider uuid'))
      ->setRequired(TRUE);

    $properties['target'] = DataDefinition::create('string')
      ->setLabel(t('Target (file uuid or hidden)'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('provider_uuid')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $max_length = 36;
    $constraints[] = $constraint_manager->create('ComplexData', [
      'provider_uuid' => [
        'Length' => [
          'max' => $max_length,
          'maxMessage' => $this->t('%name: the provider uuid may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
            '@max' => $max_length,
          ]),
        ],
      ],
    ]);
    $constraints[] = $constraint_manager->create('ComplexData', [
      'target' => [
        'Length' => [
          'max' => $max_length,
          'maxMessage' => $this->t('%name: the target may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
            '@max' => $max_length,
          ]),
        ],
      ],
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['provider_uuid'] = \Drupal::service('uuid')->generate();
    $values['target'] = \Drupal::service('uuid')->generate();
    return $values;
  }

}
