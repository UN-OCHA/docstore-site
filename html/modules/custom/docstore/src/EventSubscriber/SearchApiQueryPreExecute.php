<?php

namespace Drupal\docstore\EventSubscriber;

use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\ConditionGroupInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds Facets support to a Search API Query.
 */
class SearchApiQueryPreExecute implements EventSubscriberInterface {

  /**
   * The facet manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * Constructs a new event subscriber.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager
   *   The facet manager.
   */
  public function __construct(DefaultFacetManager $facet_manager) {
    $this->facetManager = $facet_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SearchApiEvents::QUERY_PRE_EXECUTE][] = ['includeFacetsInQuery'];
    return $events;
  }

  /**
   * Alter the Search API query to include Facets if enabled.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The event being emitted by Search API.
   */
  public function includeFacetsInQuery(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();
    if ($query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      $facet_source_id = strtr('docstore:!index', [
        '!index' => $query->getIndex()->id(),
      ]);
      $this->facetManager->alterQuery($query, $facet_source_id);

      $facets = $this->facetManager->getFacetsByFacetSourceId($facet_source_id);
      $facet_tags = [];
      $aliased_facets = [];
      foreach ($facets as $facet) {
        if ($facet->getUrlAlias() !== $facet->getFieldIdentifier()) {
          $aliased_facets[] = $facet->getUrlAlias();
        }
        if ($facet->getQueryOperator() === 'or') {
          $facet_field = $facet->getFieldIdentifier();
          $facet_tag = strtr('facet:!field', ['!field' => $facet_field]);
          $facet_tags[$facet_field] = $facet_tag;
        }
      }
      // When using the OR operator for facets a filter is created to
      // support tagging and excluding filters. The base JSON:API search will
      // add the initial filter from the URL parameters which will stomp over
      // the one that is added via facets.
      // Only way to alter these conditions is modifying by reference.
      // @see: https://www.drupal.org/project/search_api/issues/2910522#comment-12270738.
      $conditions = $query->getConditionGroup();
      $this->removeAliasConditions($conditions, $aliased_facets);
      $this->tagConditionsWithFacetTags($conditions, $facet_tags);
    }
  }

  /**
   * Removes alias conditions from the query.
   *
   * The index resource adds all filter conditions to the Search API query,
   * which includes the facet URL alias. Remove them to prevent errors for
   * non-existant fields.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The condition group.
   * @param array $aliases
   *   The facet aliases.
   */
  protected function removeAliasConditions(ConditionGroupInterface $condition_group, array $aliases) {
    $conditions = &$condition_group->getConditions();
    foreach ($conditions as $key => $condition) {
      if ($condition instanceof ConditionGroupInterface) {
        $this->removeAliasConditions($condition, $aliases);
      }
      else {
        if (in_array($condition->getField(), $aliases, TRUE)) {
          unset($conditions[$key]);
        }
      }
    }
  }

  /**
   * Handles tagging conditionGroups with appropriate facet tags as needed.
   *
   * @param \Drupal\search_api\Query\ConditionInterface|\Drupal\search_api\Query\ConditionGroupInterface $condition_or_group
   *   The ConditionGroup or Condition being evaluated.
   * @param array $facet_tags
   *   The facet tags that may need to be applied to the condition.
   *
   * @return void|string
   *   Void if the Condition is being modified, the tag to be added otherwise.
   */
  protected function tagConditionsWithFacetTags($condition_or_group, array $facet_tags) {
    if ($condition_or_group instanceof ConditionGroupInterface) {
      foreach ($condition_or_group->getConditions() as $condition) {
        $tags_to_add = $this->tagConditionsWithFacetTags($condition, $facet_tags);
        if ($tags_to_add) {
          $tags = &$condition_or_group->getTags();
          $tags[$tags_to_add] = $tags_to_add;
        }
      }
    }
    else {
      $field = $condition_or_group->getField();
      if (isset($facet_tags[$field])) {
        return $facet_tags[$field];
      }
    }
  }

}
