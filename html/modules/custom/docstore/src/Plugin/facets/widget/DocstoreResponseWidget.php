<?php

namespace Drupal\docstore\Plugin\facets\widget;

use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets\Plugin\facets\widget\ArrayWidget;

/**
 * A simple widget class that returns for inclusion in JSON:API Search API.
 *
 * @FacetsWidget(
 *   id = "docstore_facet_source",
 *   label = @Translation("Docstore"),
 *   description = @Translation("A widget that builds an array with results. Used only for integrating into docstore."),
 * )
 *
 * @note: This widget is almost identical to ArrayWidget except changing how
 * URLs are being generated as to not leak cacheable metadata.
 */
class DocstoreResponseWidget extends ArrayWidget {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $build = [
      'id' => $facet->id(),
      'label' => $facet->getName(),
      'filter' => 'proper filter name',
      'terms' => [],
    ];

    /** @var \Drupal\facets\Result\Result[] $results */
    $results = $facet->getResults();
    $items = [];

    foreach ($results as $result) {
      $text = $this->generateValues($result);
      $items[$facet->getFieldIdentifier()][] = $text;
    }

    $build['terms'] = $items;

    return $build;
  }

  /**
   * Prepares the URL and values for the facet.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   A result item.
   *
   * @return array
   *   The results.
   */
  protected function prepare(ResultInterface $result) {
    return $this->generateValues($result);
  }

  /**
   * Generates the value and the url.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   The result to extract the values.
   *
   * @return array
   *   The values.
   */
  protected function generateValues(ResultInterface $result) {
    return [
      'value' => $result->getRawValue(),
      'label' => $result->getDisplayValue(),
      'active' => $result->isActive(),
      'count' => (int) $result->getCount(),
    ];
  }

}
