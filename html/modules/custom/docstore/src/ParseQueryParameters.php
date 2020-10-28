<?php

namespace Drupal\docstore;

use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides helper methods for parsing query parameters.
 */
class ParseQueryParameters {

  /**
   * The offset key in the page parameter: page[offset].
   *
   * @var string
   */
  const OFFSET_KEY = 'offset';

  /**
   * The size key in the page parameter: page[limit].
   *
   * @var string
   */
  const SIZE_KEY = 'limit';

  /**
   * Default offset.
   *
   * @var int
   */
  const DEFAULT_OFFSET = 0;

  /**
   * Max size.
   *
   * @var int
   */
  const SIZE_MAX = 100;

  /**
   * The direction key in the sort parameter: sort[lorem][<direction>].
   *
   * @var string
   */
  const DIRECTION_KEY = 'direction';

  /**
   * The langcode key in the sort parameter: sort[lorem][<langcode>].
   *
   * @var string
   */
  const LANGUAGE_KEY = 'langcode';

  /**
   * The value key name.
   *
   * @var string
   */
  const VALUE_KEY = 'value';

  /**
   * The field key name.
   *
   * @var string
   */
  const PATH_KEY = 'path';

  /**
   * The operator key name.
   *
   * @var string
   */
  const OPERATOR_KEY = 'operator';

  /**
   * The key for the implicit root group.
   */
  const ROOT_ID = '@root';

  /**
   * Key in the filter[<key>] parameter for conditions.
   *
   * @var string
   */
  const CONDITION_KEY = 'condition';

  /**
   * Key in the filter[<key>] parameter for groups.
   *
   * @var string
   */
  const GROUP_KEY = 'group';

  /**
   * Key in the filter[<id>][<key>] parameter for group membership.
   *
   * @var string
   */
  const MEMBER_KEY = 'memberOf';

  /**
   * The root condition group.
   *
   * @var string
   */
  protected $root;

  /**
   * Parse filter parameters.
   *
   * @param array $filters
   *   The filter query string.
   *
   * @return array
   *   The tree of all the filters.
   */
  public function parseFilters(array $filters) {
    $items = $this->expand($filters);

    $root = [
      'id' => static::ROOT_ID,
      static::GROUP_KEY => [
        'conjunction' => 'AND',
      ],
    ];

    return $this->buildTree($root, $items);
  }

  /**
   * Parse sort parameters.
   *
   * @param mixed $sorters
   *   The sort query string.
   *
   * @return array
   *   The sort.
   */
  public function parseSort($sorters) {
    // Expand sort into a more expressive sort parameter.
    if (is_string($sorters)) {
      $sorters = $this->expandSortFieldString($sorters);
    }

    // Expand any defaults into the sort array.
    $expanded = [];
    foreach ($sorters as $sort_index => $sort_item) {
      $expanded[$sort_index] = $this->expandSortItem($sort_item);
    }

    return $expanded;
  }

  /**
   * Parse paging parameters.
   *
   * @param array $pagers
   *   The paging query string.
   *
   * @return array
   *   The paging info.
   */
  public function parsePaging(array $pagers) {
    $expanded = $pagers + [
      static::OFFSET_KEY => static::DEFAULT_OFFSET,
      static::SIZE_KEY => static::SIZE_MAX,
    ];

    if ($expanded[static::SIZE_KEY] > static::SIZE_MAX) {
      $expanded[static::SIZE_KEY] = static::SIZE_MAX;
    }

    return [
      'offset' => $expanded[static::OFFSET_KEY],
      'limit' => $expanded[static::SIZE_KEY],
    ];
  }

  /**
   * Apply filters to search Api query.
   *
   * @param array $filters
   *   The filters as a tree.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to append to.
   */
  public function applyFiltersToIndex(array $filters, QueryInterface &$query) {
    if (!empty($filters)) {
      $conditions = $query->createConditionGroup($filters['group']['conjunction']);
      foreach ($filters['group']['members'] as $filter) {
        if (isset($filter['group'])) {
          $subgroup = $query->createConditionGroup($filter['group']['conjunction']);
          foreach ($filter['group']['members'] as $sub) {
            $subgroup->addCondition($sub['condition']['path'], $sub['condition']['value'], $sub['condition']['operator']);
          }
          $conditions->addConditionGroup($subgroup);
        }
        else {
          $conditions->addCondition($filter['condition']['path'], $filter['condition']['value'], $filter['condition']['operator']);
        }
      }
      $query->addConditionGroup($conditions);
    }
  }

  /**
   * Apply sorters to search Api query.
   *
   * @param array $sorters
   *   The sorters as a tree.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to append to.
   */
  public function applySortToIndex(array $sorters, QueryInterface &$query) {
    foreach ($sorters as $sorter) {
      $query->sort($sorter['path'], $sorter['direction']);
    }
  }

  /**
   * Apply pagers to search Api query.
   *
   * @param array $pagers
   *   The pagers as a tree.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to append to.
   */
  public function applyPagerToIndex(array $pagers, QueryInterface &$query) {
    $query->range($pagers['page'] * $pagers['limit'], $pagers['limit']);
  }

  /**
   * Expands any filter parameters using shorthand notation.
   *
   * @param array $original
   *   The unexpanded filter data.
   *
   * @return array
   *   The expanded filter data.
   */
  protected function expand(array $original) {
    $expanded = [];
    foreach ($original as $key => $item) {
      // Allow extreme shorthand filters, f.e. `?filter[promote]=1`.
      if (!is_array($item)) {
        $item = [
          static::VALUE_KEY => $item,
        ];
      }

      // Throw an exception if the query uses the reserved filter id for the
      // root group.
      if ($key == static::ROOT_ID) {
        $msg = sprintf("'%s' is a reserved filter id.", static::ROOT_ID);
        throw new \UnexpectedValueException($msg);
      }

      // Add a memberOf key to all items.
      if (isset($item[static::CONDITION_KEY][static::MEMBER_KEY])) {
        $item[static::MEMBER_KEY] = $item[static::CONDITION_KEY][static::MEMBER_KEY];
        unset($item[static::CONDITION_KEY][static::MEMBER_KEY]);
      }
      elseif (isset($item[static::GROUP_KEY][static::MEMBER_KEY])) {
        $item[static::MEMBER_KEY] = $item[static::GROUP_KEY][static::MEMBER_KEY];
        unset($item[static::GROUP_KEY][static::MEMBER_KEY]);
      }
      else {
        $item[static::MEMBER_KEY] = static::ROOT_ID;
      }

      // Add the filter id to all items.
      $item['id'] = $key;

      // Expands shorthand filters.
      $expanded[$key] = static::expandItem($key, $item);
    }

    return $expanded;
  }

  /**
   * Expands a filter item in case a shortcut was used.
   *
   * Possible cases for the conditions:
   *   1. filter[uuid][value]=1234.
   *   2. filter[0][condition][field]=uuid&filter[0][condition][value]=1234.
   *   3. filter[uuid][condition][value]=1234.
   *   4. filter[uuid][value]=1234&filter[uuid][group]=my_group.
   *
   * @param string $filter_index
   *   The index.
   * @param array $filter_item
   *   The raw filter item.
   *
   * @return array
   *   The expanded filter item.
   */
  protected static function expandItem($filter_index, array $filter_item) {
    if (isset($filter_item[static::VALUE_KEY])) {
      if (!isset($filter_item[static::PATH_KEY])) {
        $filter_item[static::PATH_KEY] = $filter_index;
      }

      $filter_item = [
        static::CONDITION_KEY => $filter_item,
        static::MEMBER_KEY => $filter_item[static::MEMBER_KEY],
      ];
    }

    if (!isset($filter_item[static::CONDITION_KEY][static::OPERATOR_KEY])) {
      $filter_item[static::CONDITION_KEY][static::OPERATOR_KEY] = '=';
    }

    return $filter_item;
  }

  /**
   * Organizes the flat, normalized filter items into a tree structure.
   *
   * @param array $root
   *   The root of the tree to build.
   * @param array $items
   *   The normalized entity conditions and groups.
   *
   * @return \Drupal\jsonapi\Query\EntityConditionGroup
   *   The entity condition group
   */
  protected static function buildTree(array $root, array $items) {
    $id = $root['id'];

    // Recursively build a tree of denormalized conditions and condition groups.
    $members = [];
    foreach ($items as $item) {
      if ($item[static::MEMBER_KEY] == $id) {
        if (isset($item[static::GROUP_KEY])) {
          array_push($members, static::buildTree($item, $items));
        }
        elseif (isset($item[static::CONDITION_KEY])) {
          array_push($members, $item);
        }
      }
    }

    $root[static::GROUP_KEY]['members'] = $members;

    return $root;
  }

  /**
   * Expands a simple string sort into a more expressive sort that we can use.
   *
   * @param string $fields
   *   The comma separated list of fields to expand into an array.
   *
   * @return array
   *   The expanded sort.
   */
  protected static function expandSortFieldString($fields) {
    return array_map(function ($field) {
      $sort = [];

      if ($field[0] == '-') {
        $sort[static::DIRECTION_KEY] = 'DESC';
        $sort[static::PATH_KEY] = substr($field, 1);
      }
      else {
        $sort[static::DIRECTION_KEY] = 'ASC';
        $sort[static::PATH_KEY] = $field;
      }

      return $sort;
    }, explode(',', $fields));
  }

  /**
   * Expands a sort item in case a shortcut was used.
   *
   * @param array $sort_item
   *   The raw sort item.
   *
   * @return array
   *   The expanded sort item.
   */
  protected function expandSortItem(array $sort_item) {
    $defaults = [
      static::DIRECTION_KEY => 'ASC',
      static::LANGUAGE_KEY => NULL,
    ];

    if (!isset($sort_item[static::PATH_KEY])) {
      throw new BadRequestHttpException('You need to provide a field name for the sort parameter.');
    }

    $expected_keys = [
      static::PATH_KEY,
      static::DIRECTION_KEY,
      static::LANGUAGE_KEY,
    ];

    $expanded = array_merge($defaults, $sort_item);

    // Verify correct sort keys.
    if (count(array_diff($expected_keys, array_keys($expanded))) > 0) {
      throw new BadRequestHttpException('You have provided an invalid set of sort keys.');
    }

    return $expanded;
  }

}
