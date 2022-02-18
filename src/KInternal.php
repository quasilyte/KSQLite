<?php

namespace KSQLite3;

/**
 * KInternal is a shared utility code that is used inside KSQLite3
 * library internally. Please avoid using it outside of this package.
 */
class KInternal {
  public const OK = 0;
  public const ROW = 100;
  public const DONE = 101;

  /**
   * @param ffi_scope<sqlite> $lib
   * @param ffi_cdata<sqlite, struct sqlite3_stmt*> $stmt
   * @return mixed
   */
  public static function getColumnValue($lib, $stmt, int $column) {
    $typ = $lib->sqlite3_column_type($stmt, $column);
    switch ($typ) {
    case KSQLite3Result::TYPE_INTEGER:
      return $lib->sqlite3_column_int64($stmt, $column);
    case KSQLite3Result::TYPE_FLOAT:
      return $lib->sqlite3_column_double($stmt, $column);
    case KSQLite3Result::TYPE_TEXT:
      return $lib->sqlite3_column_text($stmt, $column);
    case KSQLite3Result::TYPE_NULL:
      return null;
    case KSQLite3Result::TYPE_BLOB:
        $blob = $lib->sqlite3_column_blob($stmt, $column);
        $blob_size = $lib->sqlite3_column_bytes($stmt, $column);
        return \FFI::string($blob, $blob_size);

    default:
      // Should never happen.
      throw new \Exception('internal library error: unsupported SQLite value type');
    }
  }
}
