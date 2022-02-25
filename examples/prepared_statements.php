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

// The easiest way to bind parameters is to do so by index.
// Note: the indexes start from 1.
$ok = $db->exec('INSERT INTO fav_numbers(num_value) VALUES (?1), (?2)', [
  // Sometimes your source values can differ from the SQL table types.
  // Or you may want to bind string to BLOB instead of TEXT.
  // In these cases, explicitly typed bindings can be used.
  1 => [KSQLite::TYPE_INTEGER, 100.6],
  2 => [KSQLite::TYPE_INTEGER, 200.1],
]);
if (!$ok) {
  handle_error(__LINE__, 'exec/insert', $db->getLastError());
}

// Named params are supported as well.
// Note: named param keys should contain a prefix symbol ':'.
$ok = $db->exec('INSERT INTO fav_numbers(num_value) VALUES(:num_value)', [
  ':num_value' => 200,
]);
if (!$ok) {
  handle_error(__LINE__, 'exec/insert', $db->getLastError());
}

$values = [rand(0, 999999), rand(0, 999999)];
$ok = $db->execPrepared(
  'INSERT INTO fav_numbers(num_value) VALUES(?1)',
  function(KSQLiteParamsBinder $binder) use ($values) {
    if ($binder->query_index >= count($values)) {
      return false; // No more rows to insert, stop now
    }
    // Bind ?1 to the specified value.
    $binder->bind(1, $values[$binder->query_index]);
    return true; // Parameters bound, execute the query
  }
);
if (!$ok) {
  handle_error(__LINE__, 'execPrepared/insert', $db->getLastError());
}

$values = [rand(-999999, -1), rand(-999999, -1)];

// Using named params with execPrepared.
$ok = $db->execPrepared(
  'INSERT INTO fav_numbers(num_value) VALUES(:num_value)',
  function(KSQLiteParamsBinder $binder) use ($values) {
    if ($binder->query_index >= count($values)) {
      return false; // No more rows to insert, stop now
    }
    // Bind num_value to the specified value.
    // Please note that you have to specify ':' here as a part of the key.
    $binder->bind(':num_value', $values[$binder->query_index]);
    return true; // Parameters bound, execute the query
  }
);
if (!$ok) {
  handle_error(__LINE__, 'execPrepared/insert', $db->getLastError());
}

// It's possible to use queryPrepared with a single params set,
// but it's not very convenient.
// Since there is only one bind, $query_seq will be 0 for all rows.
$ok = $db->queryPrepared(
  'SELECT * FROM fav_numbers WHERE num_value >= :num_value',
  function(KSQLiteParamsBinder $binder) {
    if ($binder->query_index !== 0) {
      return false;
    }
    $binder->bind(':num_value', 50);
    return true;
  },
  function(KSQLiteQueryContext $ctx) {
    var_dump($ctx->query_index . '=>' . $ctx->rowDataAssoc()['num_value']);
  }
);
if (!$ok) {
  handle_error(__LINE__, 'queryPrepared/insert', $db->getLastError());
}

// A custom mapping callback can be used to fetch data in some specific way.
// Here we collect only IDs.
[$ids, $ok] = $db->fetch('SELECT * FROM fav_numbers', [], function(KSQLiteQueryContext $ctx) {
  return $ctx->rowDataAssoc()['num_id'];
});
if (!$ok) {
  handle_error(__LINE__, 'fetch/select ids', $db->getLastError());
}
var_dump(['ids' => $ids]);

$ok = $db->queryPrepared(
  'SELECT * FROM fav_numbers WHERE num_id = :num_id',
  function(KSQLiteParamsBinder $binder) use ($ids) {
    if ($binder->query_index >= count($ids)) {
      return false;
    }
    $binder->bind(':num_id', $ids[$binder->query_index]);
    return true;
  },
  function(KSQLiteQueryContext $ctx) {
    var_dump($ctx->query_index . '=>' . $ctx->rowDataAssoc()['num_value']);
  }
);
if (!$ok) {
  handle_error(__LINE__, 'queryPrepared', $db->getLastError());
}

echo "OK\n";

function handle_error(int $line, string $op, string $error) {
  die("line $line: error: $op: $error\n");
}
