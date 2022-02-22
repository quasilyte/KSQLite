<?php

namespace KSQLite;

/**
 * KSQLiteParamsBinder is used to bind query params with their values.
 */
class KSQLiteParamsBinder {
  /**
   * $i is a query to be executed index.
   *
   * This value starts from 0 for the first query,
   * then it's increased by 1 per every query executed.
   * It's useful when using array-like bind var sources.
   * 
   * Checking $i === 0 reports whether this is a first query binding.
   */
  public int $i = 0;

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
   * @param int|string $key
   * @param mixed $value
   */
  public function bind($key, $value) {
    $this->params[$key] = $value;
  }

  public function _reset() {
    $this->params = [];
  }

  public function _getParams() {
    return $this->params;
  }
}