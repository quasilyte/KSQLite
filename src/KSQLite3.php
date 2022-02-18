<?php

namespace KSQLite3;

class KSQLite3 {
  /** @var ffi_scope<sqlite> */
  private $lib;
  /** @var ffi_cdata<sqlite, struct sqlite3*> */
  private $db;

  private string $last_error;
  
  public function __construct() {
    $this->lib = \FFI::scope('sqlite');
  }

  public static function loadFFI() {
    \FFI::load(__DIR__ . '/sqlite.h');
  }

  public function open(string $filename): bool {
    $db = $this->lib->new('struct sqlite3*');
    $retcode = $this->lib->sqlite3_open($filename, \FFI::addr($db));
    if ($retcode !== KInternal::OK) {
      $this->last_error = $this->lib->sqlite3_errstr($retcode);
      return false;
    }
    $this->db = $db;
    return true;
  }

  public function close(): bool {
    $retcode = $this->lib->sqlite3_close($this->db);
    if ($retcode !== KInternal::OK) {
      $this->last_error = $this->lib->sqlite3_errstr($retcode);
      return false;
    }
    return true;
  }

  public function getLastError(): string {
    return $this->last_error;
  }

  public function exec(string $sql): bool {
    $err = \FFI::new('char*');
    if ($this->lib->sqlite3_exec($this->db, $sql, null, null, \FFI::addr($err)) !== KInternal::OK) {
      $this->last_error = \FFI::string($err);
      $this->lib->sqlite3_free($err);
      return false;
    }
    return true;
  }

  /**
   * @return tuple(?KSQLite3Result, bool)
   */
  public function query(string $sql) {
    $stmt = $this->lib->new('struct sqlite3_stmt*');
    $retcode = $this->lib->sqlite3_prepare_v2($this->db, $sql, strlen($sql), \FFI::addr($stmt), null);
    if ($retcode !== KInternal::OK) {
      $this->last_error = $this->lib->sqlite3_errstr($retcode);
      return tuple(null, false);
    }
    return tuple(new KSQLite3Result($this->lib, $stmt), true);
  }

  /**
   * @return tuple(mixed, bool)
   */
  public function queryColumn(string $sql) {
    $stmt = $this->lib->new('struct sqlite3_stmt*');
    $retcode = $this->lib->sqlite3_prepare_v2($this->db, $sql, strlen($sql), \FFI::addr($stmt), null);
    if ($retcode !== KInternal::OK) {
      $this->last_error = $this->lib->sqlite3_errstr($retcode);
      return tuple(0, false);
    }

    $retcode = $this->lib->sqlite3_step($stmt);
    if ($retcode === KInternal::DONE) {
      $result = tuple(null, true);
    } else if ($retcode === KInternal::ROW) {
      $result = tuple(KInternal::getColumnValue($this->lib, $stmt, 0), true);
    } else {
      $this->last_error = $this->lib->sqlite3_errmsg($this->db);
      $result = tuple(0, false);
    }
    $this->lib->sqlite3_finalize($stmt);
    return $result;
  }

  /**
   * @return tuple(mixed[], bool)
   */
  public function queryRow(string $sql) {
    $stmt = $this->lib->new('struct sqlite3_stmt*');
    $retcode = $this->lib->sqlite3_prepare_v2($this->db, $sql, strlen($sql), \FFI::addr($stmt), null);
    if ($retcode !== KInternal::OK) {
      $this->last_error = $this->lib->sqlite3_errstr($retcode);
      return tuple([], false);
    }

    $retcode = $this->lib->sqlite3_step($stmt);
    if ($retcode === KInternal::DONE) {
      $result = tuple([], true);
    } else if ($retcode === KInternal::ROW) {
      $arr = [];
      $num_values = $this->lib->sqlite3_data_count($stmt);
      for ($i = 0; $i < $num_values; $i++) {
        $value = KInternal::getColumnValue($this->lib, $stmt, $i);
        $key = $this->lib->sqlite3_column_name($stmt, $i);
        $arr[$key] = $value;
      }
      $result = tuple($arr, true);
    } else {
      $this->last_error = $this->lib->sqlite3_errmsg($this->db);
      $result = tuple([], false);
    }
    $this->lib->sqlite3_finalize($stmt);
    return $result;
  }  
}
