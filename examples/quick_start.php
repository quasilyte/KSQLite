<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers.php'; // KPHP constant

use KSQLite\KSQLite;
use KSQLite\KSQLiteQueryContext;

// Before using KSQLite, it's important to load the FFI definitions once.
// For KPHP, this call can be placed in the beginning of your script.
if (KPHP) {
  KSQLite::loadFFI();
}

// Creating a new KSQLite object can't fail.
// But we're not connected to the database yet.
//
// By default, KSQLite handles have $auto_close set to true.
// It means that $db->close will be called automatically in the end of this
// script (via shutdown function).
// It's still safe to call $db->close() manually.
//
// Note: you don't have to close $db handle until you open() the actual connection.
$db = new KSQLite();

// Calling open() attempts to open the database file.
if (!$db->open('testdb')) { 
  // In the real code, you'll do some proper error-handling.
  // For the simplicity purposes, we'll just stop the script execution on errors.
  // But still, we're handling errors explicitely.
  handle_error(__LINE__, 'open', $db->getLastError());
}

$query = '
  CREATE TABLE IF NOT EXISTS languages(
    lang_id INTEGER PRIMARY KEY,
    lang_name TEXT NOT NULL,
    first_appeared INTEGER NOT NULL,
    num_elephants REAL NOT NULL
  );
';
// exec() runs a query and discards the results.
if (!$db->exec($query)) {
  handle_error(__LINE__, 'exec', $db->getLastError());
}

// fetchColumn() executes a query and returns on of its columns.
// It's like SQLite3::querySingle with $entireRow=false.
// The second return value $ok is false on errors.
[$count, $ok] = $db->fetchColumn('SELECT COUNT (*) FROM languages');
if (!$ok) {
  handle_error(__LINE__, 'fetchColumn', $db->getLastError());
}

echo "count=$count\n";

if ($count === 0) {
  echo "DB is empty, inserting values...\n";
  $rows = [
    ['PHP', 1995, 1.0],
    ['KPHP', 2014, 2.0],
    ['C', 1972, 0.0],
    ['C++', 1983, 0.0],
    ['JavaScript', 1995, 0.0],
    ['Go', 2009, 0.0],
  ];
  // For simplicity, we're using the string interpolation with VALUES
  // here, but you should never do so with untrusted inputs.
  // Use param binding API for that.
  foreach ($rows as $row) {
    [$lang_name, $first_appeared, $num_elephants] = $row;
    $query = "
      INSERT INTO languages(lang_name, first_appeared, num_elephants)
      VALUES('$lang_name', $first_appeared, $num_elephants)
    ";
    if (!$db->exec($query)) {
      handle_error(__LINE__, 'exec/insert', $db->getLastError());
    }
  }
}

// fetchRowAssoc() is like fetchColumn(), but it fetches all columns
// instead of just one.
//
// As a side note, we're using a tuple instead of false to express
// the $row type as mixed[], which is not compatible with false.
// For consistency, all methods try to report error in this way.
[$row, $ok] = $db->fetchRowAssoc('SELECT * FROM languages LIMIT 1');
if (!$ok) {
  handle_error(__LINE__, 'fetchRow', $db->getLastError());
}
var_dump(['test row' => $row]);

// fetch reads all results into an array of arrays.
[$rows, $ok] = $db->fetch('SELECT * FROM languages');
if (!$ok) {
  handle_error(__LINE__, 'fetchRow', $db->getLastError());
}
foreach ($rows as $row) {
  var_dump("$row[lang_id] => $row[lang_name]");
}

echo "OK\n";
