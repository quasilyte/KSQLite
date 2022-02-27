<?php

// KSQLite library controlls the resources really well.
// It's impossible to get a memory/resource leak due to a programming error
// as you don't ever get an object you need to manually deallocate.
//
// All user-provided handlers are executed in try/catch blocks, so
// exceptions can't bypass the resources deallocation logic
// (the exceptions themselves are then re-thrown).
//
// Even die/exit inside a callback can be handled correctly.

require_once __DIR__ . '/../vendor/autoload.php';

use KSQLite\KSQLite;
use KSQLite\KSQLiteQueryContext;

if (KPHP_COMPILER_VERSION) { KSQLite::loadFFI(); }

// The key is to use a default constructor for KSQLite object.
// It does enable auto-close and auto resources cleanup during
// the script shutdown. If DB object is created like new KSQLite(false),
// then you're on your own.
$db = new KSQLite();

if (!$db->open('testdb')) { 
  die("failed to open db: " . $db->getLastError());
}

// This function allocates a SQLite3 stmt object,
// then calls a user-provided callback.
// That callback never returns, so the library can't deallocate
// this object normally.
// We do, however, free this object inside a shutdown handler
// if $db object is managed (the default behavior).
$db->fetch('SELECT 10 AS value', [], function(KSQLiteQueryContext $ctx) {
  if (some_cond()) {
    die("exiting right from the callback\n");
  }
  return 0;
});

function some_cond() { return true; }
