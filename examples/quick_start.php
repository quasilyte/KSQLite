<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers.php'; // KPHP constant

use KSQLite3\KSQLite3;

// Before using KSQLite3, it's important to load the FFI definitions once.
// For KPHP, this call can be placed in the beginning of your script.
if (KPHP) {
  KSQLite3::loadFFI();
}

// Creating a new KSQLite3 object can't fail.
// But we're not connected to the database yet.
$db = new KSQLite3();

// Calling open() attempts to open the database file.
if (!$db->open('testdb')) { 
  // In the real code, you'll do some proper error-handling.
  // For the simplicity purposes, we'll just stop the script execution on errors.
  // But still, we're handling errors explicitely.
  die("can not connect\n");
}

$query = '
  CREATE TABLE IF NOT EXISTS languages(
    lang_id INTEGER PRIMARY KEY,
    lang_name TEXT NOT NULL,
    first_appeared INTEGER NOT NULL,
    num_elephants REAL NOT NULL
  );
';
// exec() runs a query and discards its results.
if (!$db->exec($query)) {
  die($db->getLastError() . "\n");
}

// queryColumn() executes a query and returns on of its columns.
// It's like SQLite3::querySingle with $entireRow=false.
// The second return value $ok is false on errors.
[$count, $ok] = $db->queryColumn('SELECT COUNT (*) FROM languages');
if (!$ok) {
  // Use getLastError() to get the error description.
  die('queryColumn: ' . $db->getLastError() . "\n");  
}

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
      die('exec: insert row: ' . $db->getLastError() . "\n");  
    }
  }
}

// queryRow() is like queryColumn(), but it fetches all columns
// instead of just one.
// Works identical to SQLite3::querySingle with $entireRow=true.
//
// As a side note, we're using a tuple instead of false to express
// the $row type as mixed[], which is not compatible with false.
// For consistency, all methods try to report error in this way.
[$row, $ok] = $db->queryRow('SELECT * FROM languages LIMIT 1');
if (!$ok) {
  die('queryRow: ' . $db->getLastError() . "\n"); 
}
var_dump(['test row' => $row]);

// query() is a more powerfull API that offers more control over
// the result set.
[$result, $ok] = $db->query('SELECT * FROM languages');
if (!$ok) {
  die('query: ' . $db->getLastError() . "\n"); 
}
// This is the same idiom as in SQLite3 examples.
// The only difference that we have dedicated methods for
// index and column name keys.
// Use fetchAssocArray to get SQLITE3_ASSOC behavior.
// fetchArray() returns false when there is no more
// rows to fetch (or error has occured).
while ($arr = $result->fetchArray()) {
  var_dump($arr);
}
// Since you can't distinguish the 'no rows' and 'error occured' cases,
// error handling is performed separately.
// If there is no error, empty string is returned.
if ($result->getError()) {
  die('fetching: ' . $result->getError() . "\n"); 
}
// You should always call finilize() on the result set
// when you finished working with it.
// It does not get finilized automatically.
$result->finalize();

// Don't forget to close the DB handle when you don't need it anymore.
$db->close();

echo "OK\n";
