<?php

namespace KSQLite;

class KSQLite {
  public const TYPE_INTEGER = 1;
  public const TYPE_FLOAT = 2;
  public const TYPE_TEXT = 3;
  public const TYPE_BLOB = 4;
  public const TYPE_NULL = 5;

  private bool $closed = true;

  /** @var ffi_scope<sqlite> */
  private $lib;
  /** @var ffi_cdata<sqlite, struct sqlite3*> */
  private $db;

  private string $last_error = '';
  
  public function __construct(bool $auto_close = true) {
    $this->lib = \FFI::scope('sqlite');
    if ($auto_close) {
      KShutdownHandler::pushDatabase($this);
    }

    if (false) {
      $this->exec('');
      $this->execPrepared('', null);
      $this->fetch('');
      $this->fetchRow('');
      $this->fetchRowAssoc('');
      $this->fetchColumn('');
      $this->query('', [], null);
      $this->queryPrepared('', null, null);
    }
  }

  public static function loadFFI() {
    \FFI::load(__DIR__ . '/sqlite.h');
  }

  public static function columnTypeName(int $type): string {
    return KInternal::columnTypeName($type);
  }

  public function open(string $filename): bool {
    $db = $this->lib->new('struct sqlite3*');
    $retcode = $this->lib->sqlite3_open($filename, \FFI::addr($db));
    if ($retcode !== KInternal::OK) {
      $this->last_error = $this->lib->sqlite3_errstr($retcode);
      return false;
    }
    $this->closed = false;
    $this->db = $db;
    return true;
  }

  public function close(): bool {
    if ($this->closed) {
      return true;
    }
    $this->closed = true;

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

  /**
   * @param string $sql query to be executed, with or without bind params
   * @param mixed[] $params query bind params
   * @return bool operation status; use getLastError if false is returned
   */
  public function exec(string $sql, array $params = []): bool {
    return $this->execPrepared($sql, function(KSQLiteParamsBinder $b) use ($params) {
      if ($b->query_index !== 0) { return false; }
      foreach ($params as $k => $v) { $b->bind($k, $v); }
      return true;
    });
  }

  /**
   * @param string $sql query to be executed, with or without bind params
   * @param callable(KSQLiteParamsBinder):boolean $bind_params
   * @return bool operation status; use getLastError if false is returned
   */
  public function execPrepared(string $sql, callable $bind_params): bool {
    return $this->doQuery($sql, $bind_params, function ($stmt, int $query_index) {
      $retcode = $this->lib->sqlite3_step($stmt);
        if ($retcode !== KInternal::DONE && $retcode !== KInternal::ROW) {
          $this->last_error = $this->lib->sqlite3_errstr($retcode);
          return false;
        }
        return true;
    });
  }

  /**
   * @param string $sql query to be executed, with or without bind params
   * @param mixed[] $params query bind params
   * @param callable(KSQLiteQueryContext):mixed $data_map_func
   * @return tuple(mixed[],bool) fetched data along with the operation status
   */
  public function fetch(string $sql, array $params = [], callable $data_map_func = null) {
    if ($data_map_func !== null) {
      return $this->doFetch($sql, $params, $data_map_func);
    }
    return $this->doFetch($sql, $params, function(KSQLiteQueryContext $ctx) {
      return $ctx->rowDataAssoc();
    });
  }

  /**
   * @param string $sql query to be executed, with or without bind params
   * @param mixed[] $params query bind params
   * @return tuple(mixed[],bool) fetched data along with the operation status
   */
  public function fetchRow(string $sql, array $params = []) {
    return $this->doFetchRow($sql, $params, false);
  }

  /**
   * fetchRowAssoc is like fetchRow, but result array has string name keys instead of indexes.
   * @param string $sql query to be executed, with or without bind params
   * @param mixed[] $params query bind params
   * @return tuple(mixed[],bool) fetched data along with the operation status
   */
  public function fetchRowAssoc(string $sql, array $params = []) {
    return $this->doFetchRow($sql, $params, true);
  }

  /**
   * @param string $sql query to be executed, with or without bind params
   * @param mixed[] $params query bind params
   * @return tuple(mixed,bool) fetched data along with the operation status
   */
  public function fetchColumn(string $sql, array $params = []) {
    $result = new KSQLiteArray();
    $ok = $this->queryPrepared($sql, function(KSQLiteParamsBinder $b) use ($params) {
      if ($b->query_index !== 0) { return false; }
      foreach ($params as $k => $v) { $b->bind($k, $v); }
      return true;
    }, function (KSQLiteQueryContext $ctx) use ($result) {
      $num_cols = $ctx->numColumns();
      if ($num_cols !== 1) {
        $this->last_error = "expected 1 column, got $num_cols";
        $ctx->stop();
        return;
      }
      if ($ctx->index !== 0) {
        $this->last_error = 'got more than 1 result rowset';
        $ctx->stop();
        return;
      }
      $result->values[] = $ctx->rowData()[0];
    });
    return tuple($result->values[0], $ok);
  }

  /**
   * @param string $sql query to be executed, with or without bind params
   * @param mixed[] $params query bind params
   * @param callable(KSQLiteQueryContext):void $row_func
   * @return bool operation status; use getLastError if false is returned
   */
  public function query(string $sql, array $params, callable $row_func) {
    return $this->queryPrepared($sql, function(KSQLiteParamsBinder $b) use ($params) {
      if ($b->query_index !== 0) { return false; }
      foreach ($params as $k => $v) { $b->bind($k, $v); }
      return true;
    }, $row_func);
  }

  /**
   * @param string $sql query to be executed, with or without bind params
   * @param callable(KSQLiteParamsBinder):boolean $bind_params
   * @param callable(KSQLiteQueryContext):void $row_func
   * @return bool operation status; use getLastError if false is returned
   */
  public function queryPrepared(string $sql, callable $bind_params, callable $row_func) {
    return $this->doQuery($sql, $bind_params, function ($stmt, int $query_index) use ($row_func) {
      /** @var ?KSQLiteQueryContext */
      $ctx = null;
      $data_count = -1;
      $row_i = 0;
      while (true) {
        $retcode = $this->lib->sqlite3_step($stmt);
        if ($retcode === KInternal::DONE) {
          return true;
        }

        if ($retcode === KInternal::ROW) {
          if ($data_count === -1) {
            $data_count = $this->lib->sqlite3_data_count($stmt);
            $ctx = new KSQLiteQueryContext($this->lib, $stmt, $data_count);
          }

          $ctx->query_index = $query_index;
          $row_func($ctx);
          if ($ctx->_isStopped()) {
            return true;
          }

          // Incrementing and assigning a local var to ignore the fact
          // that user can modify the $ctx->i field.
          $row_i++;
          $ctx->index = $row_i;
          $ctx->_resetRow();
          continue;
        }
      }

      return true;
    });
  }

  /**
   * @param string $sql query to be executed, with or without bind params
   * @param mixed[] $params query bind params
   * @param callable(KSQLiteQueryContext):mixed $data_map_func
   * @return tuple(mixed[],bool) fetched data along with the operation status
   */
  private function doFetch(string $sql, array $params, callable $data_map_func) {
    $result = new KSQLiteArray();
    $ok = $this->queryPrepared($sql, function(KSQLiteParamsBinder $b) use ($params) {
      if ($b->query_index !== 0) { return false; }
      foreach ($params as $k => $v) { $b->bind($k, $v); }
      return true;
    }, function (KSQLiteQueryContext $ctx) use ($result, $data_map_func) {
      $result->values[] = $data_map_func($ctx);
    });
    return tuple($result->values, $ok);
  }

  private function doFetchRow(string $sql, array $params, bool $assoc) {
    $result = new KSQLiteArray();
    $ok = $this->queryPrepared($sql, function(KSQLiteParamsBinder $b) use ($params) {
      if ($b->query_index !== 0) { return false; }
      foreach ($params as $k => $v) { $b->bind($k, $v); }
      return true;
    }, function (KSQLiteQueryContext $ctx) use ($result, $assoc) {
      if ($ctx->index !== 0) {
        $this->last_error = 'got more than 1 result rowset';
        $ctx->stop();
        return;
      }
      if ($assoc) {
        $result->values[] = $ctx->rowDataAssoc();
      } else {
        $result->values[] = $ctx->rowData();
      }
    });
    return tuple($result->values, $ok);
  }

  /**
   * @param string $sql
   * @param callable(KSQLiteParamsBinder):boolean $bind_params
   * @param callable(ffi_cdata<sqlite, struct sqlite3_stmt*>,int):boolean $result_func
   */
  private function doQuery(string $sql, callable $bind_params, callable $result_func): bool {
    return $this->doWithStatement($sql, function ($stmt) use ($bind_params, $result_func) {
      $binder = new KSQLiteParamsBinder();
      $query_seq = 0;

      while (true) {
        $params_bound = $bind_params($binder);
        if (!$params_bound) {
          break;
        }
        if ($query_seq !== 0) {
          // Using this stmt object not for the first time: need to reset it.
          $retcode = $this->lib->sqlite3_reset($stmt);
          if ($retcode !== KInternal::OK) {
            $this->last_error = $this->lib->sqlite3_errstr($retcode);
            return false;
          }
          $retcode = $this->lib->sqlite3_clear_bindings($stmt);
          if ($retcode !== KInternal::OK) {
            $this->last_error = $this->lib->sqlite3_errstr($retcode); 
            return false;
          }
        }
        $query_params = $binder->_getParams();
        if (!$this->bindParams($stmt, $query_params)) {
          return false;
        }
      
        if (!$result_func($stmt, $query_seq)) {
          return false;
        }

        $query_seq++;
        $binder->query_index = $query_seq;
        $binder->_reset();
      }

      return true;
    });
  }

  /**
   * @param ffi_cdata<sqlite, struct sqlite3_stmt*> $stmt
   * @param mixed[] $params
   * @return bool
   */
  private function bindParams($stmt, $params): bool {
    foreach ($params as $key => $value) {
      $index = 0;
      if (is_string($key)) {
        $index = $this->lib->sqlite3_bind_parameter_index($stmt, $key);
        if ($index === 0) {
          $this->last_error = "binding non-existing param $key";
          return false;
        }
      } else {
        $index = (int)$key;
      }
      
      if (is_int($value)) {
        $retcode = $this->lib->sqlite3_bind_int64($stmt, $index, $value);
      } else {
        $this->last_error = "binding $key to unsupported value of type " . gettype($value);
        return false;
      }
      if ($retcode !== KInternal::OK) {
        $this->last_error = "binding $key: " . $this->lib->sqlite3_errstr($retcode);
        return false;
      }
    }
    return true;
  }

  /**
   * @param string $sql
   * @param callable(ffi_cdata<sqlite, struct sqlite3_stmt*>):boolean $fn
   * @return bool
   */
  private function doWithStatement(string $sql, callable $fn): bool {
    $stmt = $this->lib->new('struct sqlite3_stmt*');
    $retcode = $this->lib->sqlite3_prepare_v2($this->db, $sql, strlen($sql), \FFI::addr($stmt), null);
    if ($retcode !== KInternal::OK) {
      $this->last_error = $this->lib->sqlite3_errstr($retcode);
      return false;
    }

    /** @var \Throwable $exception */
    $exception = null;
    $ok = true;
    try {
      $ok = $fn($stmt);
    } catch (\Throwable $e) {
      // Will be re-throwed after we free allocated resources.
      $exception = $e;
    }

    // Free allocated resources.
    $this->lib->sqlite3_finalize($stmt);

    if ($exception !== null) {
      throw $exception;
    }
    return $ok;
  }
}
