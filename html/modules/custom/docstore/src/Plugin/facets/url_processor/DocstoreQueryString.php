<?php

namespace Drupal\docstore\Plugin\facets\url_processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\url_processor\QueryString;

/**
 * Query string URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "docstore",
 *   label = @Translation("Docstore Query string"),
 *   description = @Translation("Process facets generating in docstore filter syntax.")
 * )
 */
class DocstoreQueryString extends QueryString {

  /**
   * The query string variable.
   *
   * @var string
   *   The query string variable that holds all the facet information.
   */
  protected $filterKey = '';

  /**
   * An array of the existing non-facet filters.
   *
   * @var array
   *  The filters.
   */
  protected $originalFilters = [];

  /**
   * {@inheritdoc}
   */
  public function buildUrls(FacetInterface $facet, array $results) {
    // No results are found for this facet, so don't try to create urls.
    if (empty($results)) {
      return [];
    }
    return $results;
  }

}
