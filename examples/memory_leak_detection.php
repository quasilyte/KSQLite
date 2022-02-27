<?php

// Generally speaking, KSQLite library controlls the resources really well.
// It's impossible to get a memory/resource leak due to a programming error
// as you don't ever get an object you need to manually deallocate.
//
// All user-provided handlers are executed in try/catch blocks, so
// exceptions can't bypass the resources deallocation logic
// (the exceptions themselves are then re-thrown).
//
// It is, hovewer, hard to do anything with a callback that does exit()
// or die() inside a callback. We can't really interrupt that.
// Registering all allocated resources during the run time is quite
// expensive and unpleasant.
//
// However, KSQLite does detect these cases that can lead to the memory leaks.
// If you create a managed (auto_close=true, the default) DB handles,
// it will check that the number of allocated objects matches the number
// of deallocated objects. If it does not, a warning will be reported.
// Then you can try to fix your code to avoid exit() or die() in these places. 

require_once __DIR__ . '/../vendor/autoload.php';

use KSQLite\KSQLite;
use KSQLite\KSQLiteQueryContext;

if (KPHP_COMPILER_VERSION) { KSQLite::loadFFI(); }

$db = new KSQLite();

if (!$db->open('testdb')) { 
  die("failed to open db: " . $db->getLastError());
}

$db->fetch('SELECT 10 AS value', [], function(KSQLiteQueryContext $ctx) {
  // It's safe to throw exceptions from callbacks, but it's not
  // safe to do an exit() or die().
  die("exiting right from the callback\n");
});

// After this script (or request) is finished, this message will be printed to stderr:
// WARNING: KSQLite: allocated more than deallocated, memory leaks are possible
