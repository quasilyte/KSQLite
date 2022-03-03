<?php

use PHPUnit\Framework\TestCase;
use KSQLite\KSQLite;
use KSQLite\KSQLiteArray;
use KSQLite\KSQLiteQueryContext;
use KSQLite\KSQLiteParamsBinder;

class KSQLiteTest extends TestCase {
  private static $db_filename = '';
  private static KSQLite $db;
  private static $coord_values = [];

  public static function setUpBeforeClass(): void {
    if (KPHP_COMPILER_VERSION) { KSQLite::loadFFI(); }

    // TODO: use sys_get_temp_dir() instead of '/tmp'.
    // See https://github.com/VKCOM/kphp/issues/462
    self::$db_filename = (string)tempnam('/tmp', 'testdb');

    self::$db = new KSQLite();
    if (!self::$db->open(self::$db_filename)) {
      throw new \Exception(self::$db->getLastError());
    }

    $tables = [
      'CREATE TABLE readonly_coord(
        coord_id INTEGER PRIMARY KEY,
        layer INTEGER DEFAULT 0 NOT NULL,
        x REAL NOT NULL,
        y REAL NOT NULL
      )',
      'CREATE TABLE coord(
        coord_id INTEGER PRIMARY KEY,
        layer INTEGER DEFAULT 0 NOT NULL,
        x REAL NOT NULL,
        y REAL NOT NULL
      )',
    ];
    foreach ($tables as $q) {
      if (!self::$db->exec($q)) {
        throw new \Exception(self::$db->getLastError());
      }
    }

    $coord_values = [
      [142.5, 218.0],
      [0.0, 0.0],
      [134.2, 293.0],
      [14.2, 10.0],
      [1.0, 1.0],
    ];
    foreach ($coord_values as $i => $c) {
      [$x, $y] = $c;
      self::$coord_values[] = ['coord_id' => $i + 1, 'x' => $x, 'y' => $y];
    }
    $q = 'INSERT INTO readonly_coord(x, y) VALUES(?, ?)';
    $ok = self::$db->execPrepared($q, function(KSQLiteParamsBinder $b) use ($coord_values) {
      return $b->bindFromList($coord_values);
    });
    if (!$ok) {
      throw new \Exception(self::$db->getLastError());
    }
  }

  public static function tearDownAfterClass(): void {
    self::$db->close();
    unlink(self::$db_filename);
  }

  private function assertNoError(bool $status) {
    if (!$status) {
      $this->fail(self::$db->getLastError());
    }
  }

  public function testFetchColumn() {
    $tests = [
      0,
      102,
      4.25,
      'str value',
      '"str value with quotes"',
      null,
    ];
    $query = 'SELECT :x';
    foreach ($tests as $x) {
      $params = [':x' => $x];
      [$col, $ok] = self::$db->fetchColumn($query, $params);
      $this->assertNoError($ok);
      $this->assertSame($col, $x);
    }

    $query = 'SELECT COUNT(*) FROM readonly_coord';
    [$count, $ok] = self::$db->fetchColumn($query);
    $this->assertNoError($ok);
    $this->assertSame($count, count(self::$coord_values));
  }

  public function testFetchRow() {
    $query = 'SELECT x, y FROM readonly_coord ORDER BY coord_id LIMIT 1';
    [$row, $ok] = self::$db->fetchRow($query);
    $this->assertNoError($ok);
    $this->assertSame($row[0], self::$coord_values[0]['x']);
    $this->assertSame($row[1], self::$coord_values[0]['y']);
  }

  public function testFetchRowAssoc() {
    $query = 'SELECT * FROM readonly_coord ORDER BY coord_id LIMIT 1';
    [$row, $ok] = self::$db->fetchRowAssoc($query);
    $this->assertNoError($ok);
    $this->assertSame($row['x'], self::$coord_values[0]['x']);
    $this->assertSame($row['y'], self::$coord_values[0]['y']);
  }

  public function testFetch() {
    $query = 'SELECT * FROM readonly_coord ORDER BY coord_id';
    [$rows, $ok] = self::$db->fetch($query);
    $this->assertNoError($ok);
    $this->assertSame(count($rows), count(self::$coord_values));
    foreach ($rows as $i => $row) {
      $this->assertSame($row['coord_id'], self::$coord_values[$i]['coord_id']);
      $this->assertSame($row['x'], self::$coord_values[$i]['x']);
      $this->assertSame($row['y'], self::$coord_values[$i]['y']);
    }
  }

  public function testQueryStop() {
    $obj = new KSQLiteArray();
    $query = 'SELECT coord_id FROM readonly_coord ORDER BY coord_id';
    $ok = self::$db->query($query, [], function(KSQLiteQueryContext $ctx) use ($obj) {
      if ($ctx->index == 2) {
        $ctx->stop();
        return;
      }
      $obj->values[] = $ctx->rowData();
    });
    $this->assertNoError($ok);
    $this->assertSame(2, count($obj->values));
  }

  public function testColumnTypes() {
    $query = 'SELECT * FROM readonly_coord LIMIT 1';
    [$types, $ok] = self::$db->fetch($query, [], function(KSQLiteQueryContext $ctx) {
      $column_types = [];
      for ($i = 0; $i < $ctx->numColumns(); $i++) {
        $column_types[] = KSQLite::columnTypeName($ctx->columnType($i));
      }
      return $column_types;
    });
    $this->assertNoError($ok);
    $this->assertSame(1, count($types));
    $this->assertSame($types[0], ['integer', 'integer', 'real', 'real']);
  }
}
