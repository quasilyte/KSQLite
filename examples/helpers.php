<?php

#ifndef KPHP
define('KPHP', false);
if (false)
#endif
  define('KPHP', true);

function handle_error(int $line, string $op, string $error) {
  die("line $line: error: $op: $error\n");
}
