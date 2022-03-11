<?php

$libname = 'libsqlite3';

if (!function_exists('str_contains')) {
  function str_contains(string $haystack, string $needle) {
    return empty($needle) || strpos($haystack, $needle) !== false;
  }
}

[$libs, $err] = locate_library($libname);
if ($err) {
  die("error: $err\n");
}
if (count($libs) === 0) {
  die("can't locate $libname, maybe it's not installed\n");
}
$q_mode = !empty($argv[1]) && $argv[1] === '-q';
if ($q_mode) {
  $k = array_key_last($libs);
  if ($k === null) {
    die("error: found no library candidates\n");
  } else {
    $lib_link = $libs[$k];
    echo "$lib_link\n";
  }
} else {
  $lib_link = '';
  foreach ($libs as $lib) {
    echo "library candidate: $lib\n";
    $lib_link = $lib;
  }
  echo "\n";
  echo "run something like this to make it discoverable (unix):\n";
  echo "\tmkdir -p ffilibs && sudo ln -s $lib_link ./ffilibs/$libname\n";
}

function locate_library(string $name) {
  $sys = strtoupper(php_uname('s'));
  if ($sys === 'LINUX') {
    return locate_library_linux($name);
  }
  return tuple([], "can't locate libraries on $sys system");
}

function locate_library_linux(string $name) {
  $key = strtolower($name) . '.so';
  [$ldconfig_out, $err] = exec_command(
    ['ldconfig', '/usr/sbin/ldconfig', '/sbin/ldconfig', '/bin/ldconfig'],
    '-p'
  );
  if ($err) {
    return tuple([], $err);
  }
  $lines = explode("\n", $ldconfig_out);
  $candidates = [];
  foreach ($lines as $line) {
    $parts = explode(' => ', $line);
    if (count($parts) !== 2) {
      continue;
    }
    [$lib, $link] = $parts;
    $lib = trim($lib);
    if (str_contains(strtolower($lib), $key)) {
      $candidates[$lib] = $link;
    }
  }
  return tuple($candidates, '');
}

#ifndef KPHP
function tuple(...$args) { return $args; }
#endif

function exec_command(array $commands, string $args) {
  foreach ($commands as $cmd) {
    $result = shell_exec("$cmd $args");
    if (is_string($result)) {
      return tuple($result, '');
    }
  }
  return tuple('', "can't exec " . implode('/', $commands));
}
