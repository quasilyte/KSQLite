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
   * $value types mapping:
   * - bool: INTEGER type
   * - int: INTEGER type
   * - float: REAL type
   * - string: TEXT type
   * - null value: NULL
   * 
   * Note that you can't bind BLOB-typed values with this method.
   * See bindBlob().
   * 
   * @param int|string $key
   * @param mixed $value
   */
  public function bind($key, $value) {
    $this->params[$key] = $value;
  }

  /**
   * bindBlob is like bind(), but dedicated to binding BLOB-typed values.
   */
  public function bindBlob($key, string $value) {
    $this->params[$key] = [$value];
  }

  public function _reset() {
    $this->params = [];
  }

  public function _getParams() {
    return $this->params;
  }
}
