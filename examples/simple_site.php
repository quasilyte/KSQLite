<?php

require_once __DIR__ . '/../vendor/autoload.php';

use KSQLite\KSQLite;
use KSQLite\KSQLiteQueryContext;
use KSQLite\KSQLiteParamsBinder;

// To run with PHP:
// $ php -d opcache.preload=./examples/preload.php -S localhost:8888
//
// To run with KPHP:
// $ kphp --enable-ffi --composer-root $(pwd) ./examples/simple_site.php
// $ ./kphp_out/server --http-port 8888
//
// Then visit http://localhost:8888/examples/simple_site.php in your browser.

if (KPHP_COMPILER_VERSION) { KSQLite::loadFFI(); }

try {
  $err = main();
  if ($err) {
    die('internal server error: ' . $err);
  }
} catch (\Throwable $e) {
  die('internal server error: ' . $e->getMessage());
}

function main(): string {
  $db = new KSQLite();
  if (!$db->open('testdb')) {
    return $db->getLastError();
  }
  $err = create_tables($db);
  if ($err) {
    return $err;
  }

  if (array_key_exists('delete_all', $_POST)) {
    $err = delete_all_tasks($db);
    if ($err) {
      return $err;
    }
  } else if (array_key_exists('task_name', $_POST)) {
    $new_task = [
      'task_creation_time' => time(),
      'task_priority' => (float)$_POST['task_priority'],
      'task_name' => (string)$_POST['task_name'],
      'task_description' => (string)$_POST['task_description'],
    ];
    $err = insert_task($db, $new_task);
    if ($err) {
      return $err;
    }
  }

  $select_tasks = 'SELECT *
                   FROM app_tasks
                   ORDER BY task_creation_time DESC
                   LIMIT 100';
  [$tasks, $ok] = $db->fetch($select_tasks);
  if (!$ok) {
    return $db->getLastError();
  }

  echo build_page_html($tasks);

  return '';
}

function create_tables(KSQLite $db): string {
  $create_tasks_table = 'CREATE TABLE IF NOT EXISTS app_tasks(
    task_id INTEGER PRIMARY KEY,
    task_creation_time INTEGER NOT NULL,
    task_name TEXT NOT NULL,
    task_priority REAL NOT NULL,
    task_description TEXT NOT NULL
  );';
  if (!$db->exec($create_tasks_table)) {
    return $db->getLastError();
  }
  return '';
}

function delete_all_tasks(KSQLite $db): string {
  if (!$db->exec('DELETE FROM app_tasks')) {
    return $db->getLastError();
  }
  return '';
}

function insert_task(KSQLite $db, $task): string {
  $query = 'INSERT INTO
            app_tasks(task_creation_time, task_priority, task_name, task_description)
            VALUES (?1, ?2, ?3, ?4)';
  $params = [
    1 => $task['task_creation_time'],
    2 => $task['task_priority'],
    3 => $task['task_name'],
    4 => $task['task_description'],
  ];
  if (!$db->exec($query, $params)) {
    return $db->getLastError();
  }
  return '';
}

function build_page_html(array $tasks) {
  $rows_html = '';
  foreach ($tasks as $i => $task) {
    $num = $i + 1;
    $date = date(DATE_ATOM, $task['task_creation_time']);
    $rows_html .= '<tr>';
    $rows_html .= "<td>$num</td>";
    $rows_html .= "<td>$date</td>";
    $rows_html .= "<td>$task[task_priority]</td>";
    $rows_html .= "<td>$task[task_name]</td>";
    $rows_html .= "<td>$task[task_description]</td>";
    $rows_html .= '</tr>';
  }

  $css = get_site_styles();
  $html = "
    <!DOCTYPE HTML>
    <head>
      $css
    </head>
    <body>
      <fieldset>
        <legend>Manage tasks</legend>
        <form action='simple_site.php' method='post'>
          <p><label><input type='text' value='5.0' name='task_priority'/> Task priority</label></p>
          <p><label><input type='text' name='task_name'/> Task name</label></p>
          <p><label><input type='text' name='task_description'/> Task description</label></p>
          <p><input style='width: 16em' class='btn btn-success' type='submit' value='Create task'/></p>
        </form>
        <form action='simple_site.php' method='post'>
          <p><input type='text' value='1' name='delete_all' hidden=true/></p>
          <p><input style='width: 16em' class='btn btn-danger' type='submit' value='Delete all tasks'/></p>
        </form>
      </fieldset>
      Created tasks:<br>
      <table class='table'>
        <tr>
          <th>Number</th>
          <th>Created at</th>
          <th>Priority</th>
          <th>Name</th>
          <th>Description</th>
        </tr>
        $rows_html
      </table>
    </body>
  ";

  return $html;
}

function get_site_styles(): string {
  return '
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
  ';
}
