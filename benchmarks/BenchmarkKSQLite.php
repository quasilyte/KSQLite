<?php

use KSQLite\KSQLite;
use KSQLite\KSQLiteParamsBinder;

class BenchmarkKSQLite {
  private KSQLite $db;

  public function __construct() {
    if (KPHP_COMPILER_VERSION) { KSQLite::loadFFI(); }

    $this->db = new KSQLite();
    if (file_exists('benchdb')) {
      unlink('benchdb');
    }
    if (!$this->db->open('benchdb')) {
      throw new \Exception($this->db->getLastError());
    }
    $create_test_table = 'CREATE TABLE test_data(
      id INTEGER PRIMARY KEY,
      i_value INTEGER NOT NULL,
      f_value REAL NOT NULL,
      s_value TEXT NOT NULL
    )';
    if (!$this->db->exec($create_test_table)) {
      throw new \Exception($this->db->getLastError());
    }
    $insert_data = 'INSERT INTO test_data(i_value, f_value, s_value)
                    VALUES (:i, :f, :s)';
    $ok = $this->db->execPrepared($insert_data, function(KSQLiteParamsBinder $b) {
      if ($b->query_index >= 100) {
        return false;
      }
      $b->bind(':i', rand(10, 9999999));
      $b->bind(':f', (float)(rand(10, 9999999)) + 0.4);
      $b->bind(':s', 'example string data');
      return true;
    });
    if (!$ok) {
      throw new \Exception($this->db->getLastError());
    }
  }

  public function benchmarkExecSelect1() {
    if (!$this->db->exec('SELECT 1')) {
      throw new \Exception($this->db->getLastError());
    }
  }

  public function benchmarkFetchCount() {
    [$_, $ok] = $this->db->fetchColumn('SELECT COUNT(*) FROM test_data');
    if (!$ok) {
      throw new \Exception($this->db->getLastError());
    }
  }

  public function benchmarkFetchRowConst() {
    [$_, $ok] = $this->db->fetchRow("SELECT 1, 2.5, 'hello', null");
    if (!$ok) {
      throw new \Exception($this->db->getLastError());
    }
  }

  public function benchmarkFetchOneRow() {
    [$_, $ok] = $this->db->fetchRow("SELECT * FROM test_data LIMIT 1");
    if (!$ok) {
      throw new \Exception($this->db->getLastError());
    }
  }

  public function benchmarkFetchAllRows() {
    [$_, $ok] = $this->db->fetchRow("SELECT * FROM test_data");
    if (!$ok) {
      throw new \Exception($this->db->getLastError());
    }
  }
}
