<?php

namespace KSQLite3;

class KSQLite3Result {
  public const TYPE_INTEGER = 1;
  public const TYPE_FLOAT = 2;
  public const TYPE_TEXT = 3;
  public const TYPE_BLOB = 4;
  public const TYPE_NULL = 5;

  /** @var ffi_scope<sqlite> */
  private $lib;
  /** @var ffi_cdata<sqlite, struct sqlite3_stmt*> */
  private $stmt;
  private int $data_count = -1;
  private bool $finalized = false;
  private string $err = '';

  /**
   * @param ffi_cdata<sqlite, struct sqlite3_stmt*> $stmt
   */
  public function __construct($lib, $stmt) {
    $this->lib = $lib;
    $this->stmt = $stmt;
  }

  public function getError(): string {
    return $this->err;
  }

  /**
   * @return mixed[]|false
   */
  public function fetchArray() {
    $retcode = $this->lib->sqlite3_step($this->stmt);
    if ($retcode === KInternal::DONE) {
      return false;
    }
    
    if ($retcode === KInternal::ROW) {
      $arr = [];
      if ($this->data_count === -1) {
        $this->data_count = $this->lib->sqlite3_data_count($this->stmt);
      }
      for ($i = 0; $i < $this->data_count; $i++) {
        $value = KInternal::getColumnValue($this->lib, $this->stmt, $i);
        $key = $this->lib->sqlite3_column_name($this->stmt, $i);
        $arr[] = $value;
      }
      return $arr;
    }
    
    $this->err = $this->lib->sqlite3_errmsg($this->lib->sqlite3_db_handle($this->stmt));
    return false;
  }

  public function finalize() {
    if ($this->finalized) {
      return;
    }
    $this->finalized = true;
    $this->lib->sqlite3_finalize($this->stmt);
  }
}
