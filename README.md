# KSQLite

KSQLite is a FFI-based SQLite library that can be used in both PHP and KPHP.

## Installation

1. Install libsqlite3 in your system, it must be accessible via `dlopen`
2. Install this composer package to use KSQLite class inside your code

* [Ubuntu/Debian installation guide](docs/install_deb.md)
* [MacOS installation guide](docs/install_macos.md)

Below is a platform-agnostic overview.

```bash
# 1. Install the composer package itself.
$ composer require quasilyte/ksqlite

# 2. Install libsqlite3 into your system.
# We need libsqlite3.so/libsqlite3.dll/libsqlite.dylib files to be available.

# 3. Make sure your system dynamic library loader can find the library
# with "libsqlite3" path (without suffixes).
# MacOS and Linux use dlopen(), Windows uses LoadLibrary.
```

> Note: KPHP doesn't work on Windows yet.

## Examples

* [quick_start.php](examples/quick_start.php) - a simple overview of the API basics
* [prepared_statements.php](examples/prepared_statements.php) - how to re-use a single statement for multiple queries
* [transactions.php](examples/transactions.php) - how to use transactions plus some best practices
* [simple_site.php](examples/simple_site.php) - serving a simple TODO app using SQLite as a database

Running examples with PHP:

```bash
$ php -d opcache.enable_cli=1\
      -d opcache.preload=./examples/preload.php\
      -f ./examples/transactions.php
```

Running examples with KPHP:

```bash
# Step 1: compile the example:
$ kphp --enable-ffi --mode cli --composer-root $(pwd) ./examples/transactions.php
# Step 2: run the binary:
$ ./kphp_out/cli
```

## API reference

All functions report error with `false` return value (operation status).

When there is more than one result to be returned, a tuple like `tuple(T, bool)` is returned, where second tuple element is an operation status.

If operation status is `false`, use `KSQLite::getLastError()` to get the actual error message.

Note that you only need to care about closing the opened database object. There are no other
resources you need to finalize. The API is designed in a way that you don't get any FFI-allocated
object, so the library can manage these resources for you.

* exec methods run the query while discarding their results
* fetch methods collect and return results
* query method is a low-level result set iteration privimite; fetch methods are built on that

### exec

```php
function exec(string $sql, array $params = []): bool
```

* `$sql` SQL query string with optional bind var placeholders
* `$params` bind variables for the query

When to use: need to execute a query once, but don't need the results.

```php
// Simple case: not bind params.
$query = 'CREATE TABLE IF NOT EXISTS languages(
  lang_id INTEGER PRIMARY KEY,
  lang_name TEXT NOT NULL
);'
if (!$db->exec($query)) {
  handle_error($db->getLastError());
}

// Exec with named params.
// Note: a var prefix (':', '@' or '$') should be consistent
// between the query and bind params array.
$query = 'INSERT INTO languages(lang_name) VALUES(:lang_name)';
$params = [':lang_name' => 'KPHP'];
if (!$db->exec($query, $params)) {
  handle_error($db->getLastError());
}

// Exec with positional params.
// Note: bind var numbers start from 1.
$query = 'DELETE FROM languages WHERE lang_name = ?1 OR lang_name = ?2';
$params = [1 => 'COBOL', 2 => 'Visual COBOL'];
if (!$db->exec($query, $params)) {
  handle_error($db->getLastError());
}
```

### execPrepared

```php
function execPrepared(string $sql, $bind_params_func): bool
```

* `$sql` SQL query string with optional bind var placeholders
* `$bind_params_func` a callback that binds variables for the query

When to use: running a single SQL statement with different params, don't need the results.

```php
// Execute several inserts with different bind var sets.
$values = [10, 20, 30, 40];
$query = 'INSERT INTO fav_numbers(num_value) VALUES(?1)';
$ok = $db->execPrepared($query, function(KSQLiteParamsBinder $b) use ($values) {
  if ($b->query_index >= count($values)) {
    return false; // No more rows to insert, stop now
  }
  // Bind ?1 to the specified value.
  // Use string keys, like ':num_value', to bind named params.
  $b->bind(1, $values[$b->query_index]);
  return true; // Parameters bound, execute the query
});
if (!$ok) {
  handle_error($db->getLastError());
}

// Execute 10 inserts without bind vars.
$query = "INSERT INTO fav_events(event_time) VALUES(time('now'))";
$ok = $db->execPrepared($query, function(KSQLiteParamsBinder $b) {
  return $b->query_index < 10;
});
if (!$ok) {
  handle_error($db->getLastError());
}

// Prepared statement API allows you to perform N queries
// using the same statement even if you don't know the exact
// N in advance.
$query = "INSERT INTO important_data(x, y) VALUES(:x, :y)";
$ok = $db->execPrepared($query, function(KSQLiteParamsBinder $b) use ($stream) {
  // Note: we're not even using $b->index here as our data stream is statefull
  // and it knows which item we're processing right now.
  if (!$stream->hasMore()) {
    return false;
  }
  $stream->next();
  foreach ($stream->keyValue() as $k => $v) {
    $b->bind($k, $v);
  }
  return true;
});
if (!$ok) {
  handle_error($db->getLastError());
}
```

If you find prepared statements API too low-level, consider wrapping it
in some helper functions.

### fetch

```php
function fetch(string $sql, array $params = [], $row_func = null): tuple(mixed, bool)
```

* `$sql` SQL query string with optional bind var placeholders
* `$params` bind variables for the query
* `$row_func` a callback that is called for every row, its return value is collected

If `$row_func` is null, default mapping behavior is used (`rowAssoc`).

When to use: execute a query once, collect results.

```php
// The simplest case: no bind params, default mapping function, collecting all results.
// The result rows are arrays of [x, y].
$query = 'SELECT x, y FROM tab';
[$rows, $ok] = $db->fetch($query);
if (!$ok) {
  handle_error($db->getLastError());
}
foreach ($rows as $i => [$x, $y]) {
  var_dump([$i => "x=$x y=$y"]);
}

// Using the same query, but building the result with assoc arrays,
// like ['x' => $x, 'y' => $y].
[$rows, $ok] = $db->fetch($query, [], function(KSQLiteQueryContext $ctx) {
  return $ctx->rowDataAssoc();
});
if (!$ok) {
  handle_error($db->getLastError());
}
foreach ($rows as $i => $data) {
  var_dump([$i => "x=$data['x'] y=$data['y']"]);
}

// If you return a non-array value from fetch, you'll get a flat array in the final result.
$query = 'SELECT id, second_key FROM users WHERE age >= ?1';
$vars = [1 => 18];
[$ids, $ok] = $db->fetch($query, $vars, function(KSQLiteQueryContext $ctx) {
  return $ctx->rowDataAssoc()['id'];
});
if (!$ok) {
  handle_error($db->getLastError());
}
foreach ($ids as $i => $id) {
  var_dump([$i => "id=$id"]);
}
```

Notes:

* You can stop the results fetching by using `$ctx->stop()`
* Use empty array for `$params` if your query has no bind vars, but you need a custom `$row_func`

### fetchRow

```php
function fetchRow(string $sql, array $params = []): tuple(mixed[], bool)
```

* `$sql` SQL query string with optional bind var placeholders
* `$params` bind variables for the query

When to use: execute a query once, collecting exactly one result row.

```php
$query = 'SELECT * FROM users WHERE user_id = :id';
[$user, $ok] = $db->fetchRow($query, [':id' => $id]);
if (!$ok) {
  handle_error($db->getLastError());
}
```

Note: if query returns more than one row, error will be reported.
Either use `LIMIT 1` or other ways to request only 1 row from the database,
or use `fetch()` method and skip rest of the rows explicitely.

### fetchRowAssoc

Like `fetchRow`, but result array has column name keys instead of indexes.

### fetchColumn

```php
function fetchColumn(string $sql, array $params = []): tuple(mixed, bool)
```

* `$sql` SQL query string with optional bind var placeholders
* `$params` bind variables for the query

When to use: execute a query once, collecting exactly one result column.

```php
$query = 'SELECT COUNT(*) FROM users';
[$num_users, $ok] = $db->fetchColumn($query);
if (!$ok) {
  handle_error($db->getLastError());
}
```

Note: if query returns more than one row or that row contains
more than one value, error will be reported.

### query

```php
function query(string $sql, array $params, $row_func): bool
```

* `$sql` SQL query string with optional bind var placeholders
* `$params` bind variables for the query
* `$row_func` a void-result callback that is called for every row

Unlike fetch-style API, it does not collect any results on its own.
Use external state to do that.

When to use: when query results are needed, but fetch API is not flexible enough.

```php
// Implementing a fetch-like operation via query.
$result = new KSQLiteArray();
$ok = $db->query($sql, $vars, function(SQLiteQueryContext $ctx) use ($result) {
  $result->values[] = $ctx->rowData();
});
if (!$ok) {
  handle_error($db->getLastError());
}
$handler->processData($result->values); // Work with unboxed [K]PHP array
```

> We're using KSQLiteArray here instead of a normal array since KPHP doesn't
> support by-reference closure captures.

### queryPrepared

```php
function queryPrepared(string $sql, $bind_params_func, $row_func): bool
```

* `$sql` SQL query string with optional bind var placeholders
* `$bind_params_func` a callback that binds variables for the query
* `$row_func` a void-result callback that is called for every row

When to use: same advantages like with `execPrepared`, but here you can collect the results.

```php
$ok = $db->queryPrepared(
  'SELECT * FROM fav_numbers WHERE num_id = :num_id',
  function(KSQLiteParamsBinder $b) use ($ids) {
    if ($b->query_index >= count($ids)) {
      return false;
    }
    $b->bind(':num_id', $ids[$b->query_index]);
    return true;
  },
  function(KSQLiteQueryContext $ctx) {
    // $ctx->query_index is 0 for the first prepared query execution.
    // The second execution will have $query_index=1 and so on.
    var_dump($ctx->query_index . '=>' . $ctx->rowDataAssoc()['num_value']);
  }
);
if (!$ok) {
  handle_error($db->getLastError());
}
```
