<?php

$libname = 'libsqlite3';

if (!function_exists('str_contains')) {
  function str_contains(string $haystack, string $needle) {
    return empty($needle) || strpos($haystack, $needle) !== false;
  }
}

$err = install_library($libname);
if ($err) {
  die("error: $err");
}

function install_library(string $name): string {
  $sys = strtoupper(php_uname('s'));
  if ($sys === 'LINUX') {
    return install_library_linux($name);
  }
  return "can't install for $sys system";
}

function install_library_linux(string $name): string {
  $key = strtolower($name) . '.so';
  $ldconfig_out = shell_exec('ldconfig -p');
  if (!is_string($ldconfig_out)) {
    return 'failed to run ldconfig';
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
  $selected_link = '';
  foreach ($candidates as $lib => $link) {
    if (!preg_match('/\.\d+$/', $lib)) {
      $selected_link = $link;
      break;
    }
    $selected_link = $link;
  }
  if (!$selected_link) {
    return "can't find $name library in your system";
  }
  $dir = dirname($selected_link);
  $new_link_name = "$dir/$name";
  if (file_exists($new_link_name)) {
    echo "$new_link_name already exists, you're ready to go\n";
  } else {
    echo "Run this command to create a suitable library link:\n";
    echo "$ sudo ln -s $selected_link $new_link_name\n";
  }
  return '';
}
