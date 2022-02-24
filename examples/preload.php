<?php

// This preload script is needed for PHP.
//
// Use something like this to enable preloading:
// php -d opcache.enable_cli=1 -d opcache.preload ./examples/preload.php

require_once __DIR__ . '/../vendor/autoload.php';

use KSQLite\KSQLite;

KSQLite::loadFFI();
