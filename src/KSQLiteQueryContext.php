<?php

namespace KSQLite;

/**
 * KSQLiteQueryContext is used to control the query results iteration.
 */
class KSQLiteQueryContext {
  /**
   * $index holds the number of the current data row.
   */
  public int $index = 0;

  /**
   * In prepared-like APIs, where several queries can be executed using
   * the same SQL statement, $query_seq reports the current query number.
   * For non-prepared calls this value is always 0 (first and only execution).
   */
  public int $query_seq = 0;

  private bool $stop = false;
  private int $row_data_count = 0;
  /** @var string[] */
  private $column_names = [];
  /** @var mixed[] */
  private $row_data = [];

  /** @var ffi_scope<sqlite> */
  private $lib;
  /** @var ffi_cdata<sqlite, struct sqlite3_stmt*> */
  private $stmt;

  /**
	 * @param ffi_scope<sqlite> $lib
   * @param ffi_cdata<sqlite, struct sqlite3_stmt*> $stmt
   * @param int $row_data_count
   */
  public function __construct($lib, $stmt, int $row_data_count) {
    $this->lib = $lib;
    $this->stmt = $stmt;
    $this->row_data_count = $row_data_count;
  }

  /**
   * stop instructs the result set iterator to discard all other data rows.
   * It's safe to call stop several times inside one callback call.
   * 
   * Note: calling stop() does not interrupt the current function flow.
   * All it does is sets the flag to stop results iteration when
   * your callback returns normally. In other words, stop() call should
   * be followed by a return statement if you wish to stop function
   * execution and results handling right now.
   */
  public function stop() {
    $this->stop = true;
  }

  /**
   * numColumns returns the number of data row columns.
   * 
   * It's more efficient than count($ctx->rowData()) when you
   * don't need the data itself.
   * 
   * @return int
   */
  public function numColumns(): int {
    return $this->row_data_count;
  }

  /**
   * rowData returns the current data row as array.
   * 
   * The first call to this function reads the data from sqlite3.
   * Other calls return the cached array for this row.
   * 
   * If KSQLite::query callback doesn't use rowData(),
   * results from sqlite3 row are discarded without extra allocations.
   * 
   * @return mixed[]
   */
  public function rowData() {
    if ($this->row_data_count === 0) {
      return [];
    }
    if (count($this->row_data) === 0) {
      for ($i = 0; $i < $this->row_data_count; $i++) {
        $value = KInternal::getColumnValue($this->lib, $this->stmt, $i);
        $this->row_data[] = $value;
      }
    }
    return $this->row_data;
  }

  /**
   * rowDataAssoc is like rowData, but array keys are column names, not indexes.
   * @return mixed[]
   */
  public function rowDataAssoc() {
    $assoc = [];
    foreach ($this->rowData() as $i => $value) {
      $assoc[$this->columnName($i)] = $value;
    }
    return $assoc;
  }

  /**
   * columnType returns a result data column type id for the given column index.
   * To get the column type name, use KSQLite::columnTypeName.
   * @return int
   */
  public function columnType($column): int {
    return $this->lib->sqlite3_column_type($this->stmt, $column);
  }

  /**
   * columnName returns a result data column name for the given column index.
   * If column index is invalid, empty string is returned.
   * @return string
   */
  public function columnName($column): string {
    if ($this->row_data_count === 0) {
      return '';
    }
    // If it's the first time user asks for column names,
    // load them from the sqlite.
    if (count($this->column_names) === 0) {
      for ($i = 0; $i < $this->row_data_count; $i++) {
        $name = $this->lib->sqlite3_column_name($this->stmt, $i);
        $this->column_names[] = $name;
      }
    }
    $int_column = (int)$column;
    // To give the same result in KPHP and PHP for out-of-bounds
    // column index, we explicitely return '' for invalid keys.
    // PHP would return null if we did a simple lookup here.
    if ($int_column < count($this->column_names)) {
      return $this->column_names[$int_column];
    }
    return '';
  }

  public function _isStopped(): bool {
    return $this->stop;
  }

  public function _resetRow() {
    $this->row_data = [];
  }
}
