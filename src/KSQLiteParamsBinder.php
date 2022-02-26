<?php

namespace KSQLite;

/**
 * KSQLiteParamsBinder is used to bind query params with their values.
 */
class KSQLiteParamsBinder {
  /**
   * $query_index is a query to be executed index.
   *
   * This value starts from 0 for the first query,
   * then it's increased by 1 per every query executed.
   * It's useful when using array-like bind var sources.
   * 
   * Checking $query_index === 0 reports whether this is a first query binding.
   */
  public int $query_index = 0;

  private $params = [];

  /**
   * bind assigns $value to a query param $key.
   * 
   * $key could be:
   * - int: param index, like 1, 2, etc (note: they start from 1)
   * - string: named param name, like ':foo' or '@foo'
   * 
   * The $key should match the associated query params.
   * 
   * Automatic $value types mapping:
   * - bool: INTEGER type
   * - int: INTEGER type
   * - float: REAL type
   * - string: TEXT type
   * - null value: NULL
   * 
   * To bind a blob value, use explicit param type notation: [$type, $value]
   * where $type is one of the KSQLite type constants, like KSQLite::TYPE_BLOB.
   * 
   * Here is an example:
   *
   * $db->fetch($query, [
   *   ':binary_text' => [KSQLite::TYPE_BLOB, $data],
   *   ':string_text' => [KSQLite::TYPE_TEXT, $s],
   *   ':auto_detected_int' => 530,
   * ]);
   * 
   * @param int|string $key
   * @param mixed $value
   */
  public function bind($key, $value) {
    $this->params[$key] = $value;
  }

  /**
   * bindFromList is a helper method for binding numeric params from lists.
   *
   * You usually use it inside params binding callback like this:
   *
   *    $db->execPrepared($query, function(KSQLiteParamsBinder $b) use ($vars) {
   *      return $b->bindFromList($vars);
   *    });
   *
   * For a query with ?1 ?2 parameters, this could be a bind array:
   *    [['a', 10], ['b', 20]]
   *
   * @param array $values array of list-like arrays for variables binding
   * @return bool whether params were bound
   */
  public function bindFromList(array $values): bool {
    if ($this->query_index >= count($values)) {
      return false; // No more rows to insert, stop now
    }
    foreach ($values[$this->query_index] as $i => $v) {
      $this->bind($i + 1, $v);
    }
    return true; // Parameters bound, execute the query
  }

  /**
   * bindFromArray is a helper method for binding params from arrays.
   *
   * You usually use it inside params binding callback like this:
   *
   *    $db->execPrepared($query, function(KSQLiteParamsBinder $b) use ($vars) {
   *      return $b->bindFromArray($vars);
   *    });
   *
   * For a query with ?1 ?1 parameters, this could be a bind array:
   *    [[1 => 'a', 2 => 10], [1 => 'b', 2 => 20]]
   *
   * For a query with :p1 :p2 parameters, this could be a bind array:
   *    [[':p1' => 'a', ':p2' => 10], [':p1' => 'b', ':p2' => 20]]
   *
   * @param array $values array of arrays for variables binding
   * @return bool whether params were bound
   */
  public function bindFromArray(array $values): bool {
    if ($this->query_index >= count($values)) {
      return false; // No more rows to insert, stop now
    }
    foreach ($values[$this->query_index] as $k => $v) {
      $this->bind($k, $v);
    }
    return true; // Parameters bound, execute the query
  }

  public function _reset() {
    $this->params = [];
  }

  public function _getParams() {
    return $this->params;
  }
}
