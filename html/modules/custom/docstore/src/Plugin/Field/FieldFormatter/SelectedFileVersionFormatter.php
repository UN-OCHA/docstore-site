<?php

namespace Drupal\docstore\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'selected_file_version' formatter.
 *
 * @FieldFormatter(
 *   id = "selected_file_version",
 *   label = @Translation("Selected file version"),
 *   field_types = {
 *     "selected_file_version"
 *   }
 * )
 */
class SelectedFileVersionFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ provider_uuid ~ ": " }}{{ target }}',
        '#context' => [
          'provider_uuid' => trim($item->provider_uuid),
          'target' => trim($item->target),
        ],
      ];
    }

    return $elements;
  }

}
