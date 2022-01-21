<?php

namespace Drupal\docstore\Plugin\facets\facet_source;

use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\facets\Exception\Exception;
use Drupal\facets\Plugin\facets\facet_source\SearchApiBaseFacetSource;
use Drupal\facets\QueryType\QueryTypePluginManager;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\QueryHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a facet source for use in JSON:API.
 *
 * @FacetsFacetSource(
 *   id = "docstore",
 *   deriver = "Drupal\docstore\Plugin\facets\facet_source\DocstoreFacetsDeriver"
 * )
 */
class DocstoreFacets extends SearchApiBaseFacetSource {
  use PluginDependencyTrait;

  /**
   * The search index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * Constructs a SearchApiBaseFacetSource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\facets\QueryType\QueryTypePluginManager $query_type_plugin_manager
   *   The query type plugin manager.
   * @param \Drupal\search_api\Utility\QueryHelper $search_results_cache
   *   The query type plugin manager.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, QueryTypePluginManager $query_type_plugin_manager, QueryHelper $search_results_cache, IndexInterface $index) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $query_type_plugin_manager, $search_results_cache);
    $this->index = $index;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $entity_type_manager = $container->get('entity_type.manager');

    $index = $entity_type_manager->getStorage('search_api_index')->load($plugin_definition['index']);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.facets.query_type'),
      $container->get('search_api.query_helper'),
      $index
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->addDependency('module', 'docstore');
    return $this->dependencies;
  }

  /**
   * Fills the facet entities with results from the facet source.
   *
   * @param \Drupal\facets\FacetInterface[] $facets
   *   The configured facets.
   */
  public function fillFacetsWithResults(array $facets) {
    // Check if the results for this search ID are already populated in the
    // query helper.
    $results = $this->searchApiQueryHelper->getResults(strtr('docstore:!index', ['!index' => $this->index->id()]));
    if (!$results instanceof ResultSetInterface) {
      return;
    }

    // Get our facet data.
    $facet_results = $results->getExtraData('search_api_facets');

    // If no data is found in the 'search_api_facets' extra data, we can stop
    // execution here.
    if ($facet_results === []) {
      return;
    }

    // Loop over each facet and execute the build method from the given query
    // type.
    foreach ($facets as $facet) {
      $configuration = [
        'query' => $results->getQuery(),
        'facet' => $facet,
        'results' => $facet_results[$facet->getFieldIdentifier()] ?? [],
      ];

      // Get the Facet Specific Query Type so we can process the results
      // using the build() function of the query type.
      $query_type = $this->queryTypePluginManager->createInstance($facet->getQueryType(), $configuration);
      $query_type->build();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
  }

  /**
   * {@inheritdoc}
   */
  public function getDataDefinition($field_name) {
    $field = $this->getIndex()->getField($field_name);
    if ($field) {
      return $field->getDataDefinition();
    }
    throw new Exception("Field with name {$field_name} does not have a definition");
  }

  /**
   * Retrieves the Search API index for this facet source.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index.
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest() {
    return TRUE;
  }

}
