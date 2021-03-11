<?php

namespace Drupal\docstore\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'selected_file_version' widget.
 *
 * @FieldWidget(
 *   id = "selected_file_version",
 *   label = @Translation("Selected file version"),
 *   field_types = {
 *     "selected_file_version"
 *   }
 * )
 */
class SelectedFileVersionWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['provider_uuid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Provider uuid'),
      '#default_value' => isset($items[$delta]->provider_uuid) ? $items[$delta]->provider_uuid : NULL,
    ];
    $element['target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target'),
      '#default_value' => isset($items[$delta]->target) ? $items[$delta]->target : NULL,
    ];
    return $element;
  }

}
