<?php

require_once __DIR__ . '/../vendor/autoload.php';

use KSQLite\KSQLite;
use KSQLite\KSQLiteQueryContext;
use KSQLite\KSQLiteParamsBinder;

if (defined('KPHP_COMPILER_VERSION')) { KSQLite::loadFFI(); }

$db = new KSQLite();

if (!$db->open('testdb')) { 
  handle_error(__LINE__, 'open', $db->getLastError());
}

$query = '
  CREATE TABLE IF NOT EXISTS fav_numbers(
    num_id INTEGER PRIMARY KEY,
    num_value INTEGER NOT NULL
  );
';
if (!$db->exec($query)) {
    handle_error(__LINE__, 'exec/create table', $db->getLastError());
}

if (!$db->exec('DELETE FROM fav_numbers')) {
  handle_error(__LINE__, 'exec/delete', $db->getLastError());
}

[$count, $ok] = $db->fetchColumn('SELECT COUNT(*) FROM fav_numbers');
if (!$ok) {
  handle_error(__LINE__, 'fetchColumn', $db->getLastError());
}
var_dump(['count before insert' => $count]);

// There are no helpers that make transactions easy to use.
// It's recommended to use patterns that work for you.
// I'll show you one of the options below (do_with_transaction), after the basic example.

// Begin a new transaction.
if (!$db->exec('BEGIN')) {
  handle_error(__LINE__, 'exec/begin', $db->getLastError());
}
// Execute some queries inside that transaction.
$error = '';
$values = [10, 20, 30];
foreach ($values as $v) {
  if (!$db->exec('INSERT INTO fav_numbers(num_value) VALUES(?1)', [1 => $v])) {
    $error = $db->getLastError();
    break;
  }
}
if ($error) {
  var_dump(['error' => $error]);
}
// Then close it with ROLLBACK or COMMIT.
if (!$db->exec('ROLLBACK')) {
  handle_error(__LINE__, 'exec/rollback', $db->getLastError());
}

[$count, $ok] = $db->fetchColumn('SELECT COUNT(*) FROM fav_numbers');
if (!$ok) {
  handle_error(__LINE__, 'fetchColumn', $db->getLastError());
}
var_dump(['count after insert and rollback' => $count]);

$ok = do_with_transaction($db, function(KSQLite $db) {
  if (!$db->exec('INSERT INTO fav_numbers(num_value) VALUES(48282)')) {
    // This `false` will force the transaction to be rolled back.
    return false;
  }
  // Returning `true` means COMMIT.
  return true;
});
if (!$ok) {
  handle_error(__LINE__, 'do_with_transaction', $db->getLastError());
}

[$count, $ok] = $db->fetchColumn('SELECT COUNT(*) FROM fav_numbers');
if (!$ok) {
  handle_error(__LINE__, 'fetchColumn', $db->getLastError());
}
var_dump(['count after insert and commit' => $count]);

/**
 * @param KSQLite $db
 * @param callable(KSQLite):boolean $fn
 */
function do_with_transaction(KSQLite $db, callable $fn): bool {
  if (!$db->exec('BEGIN')) {
    return false;
  }
  /** @var \Throwable $exception */
  $exception = null;
  try {
    $commit = $fn($db);
  } catch (\Throwable $e) {
    $db->exec('ROLLBACK');
    throw $e;
  }
  return $db->exec($commit ? 'COMMIT' : 'ROLLBACK');
}

echo "OK\n";

function handle_error(int $line, string $op, string $error) {
  die("line $line: error: $op: $error\n");
}
