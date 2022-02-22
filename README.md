# KSQLite

KSQLite is a FFI-based SQLite library that can be used in both PHP and KPHP.

## Examples

> TODO

## API reference

All functions report error with `false` return value (operation status).

When there is more than one result to be returned, a tuple like `tuple(T, bool)` is returned, where second tuple element is an operation status.

If operation status is `false`, use `KSQLite::getLastError()` to get the actual error message.

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
function execPrepared(string $sql, callable $bind_params): bool
```

* `$sql` SQL query string with optional bind var placeholders
* `$bind_params` a callback that binds variables for the query

When to use: running a single SQL statement with different params, don't need the results.

```php
// Execute several inserts with different bind var sets.
$values = [10, 20, 30, 40];
$query = 'INSERT INTO fav_numbers(num_value) VALUES(?1)';
$ok = $db->execPrepared($query, function(KSQLiteParamsBinder $b) use ($values) {
  if ($b->i >= count($values)) {
    return false; // No more rows to insert, stop now
  }
  // Bind ?1 to the specified value.
  // Use string keys, like ':num_value', to bind named params.
  $b->bind(1, $values[$b->i]);
  return true; // Parameters bound, execute the query
});
if (!$ok) {
  handle_error($db->getLastError());
}

// Execute 10 inserts without bind vars.
$query = "INSERT INTO fav_events(event_time) VALUES(time('now'))";
$ok = $db->execPrepared($query, function(KSQLiteParamsBinder $b) {
  return $b->i < 10;
});
if (!$ok) {
  handle_error($db->getLastError());
}

// Prepared statement API allows you to perform N queries
// using the same statement even if you don't know the exact
// N in advance.
$query = "INSERT INTO important_data(x, y) VALUES(:x, :y)";
$ok = $db->execPrepared($query, function(KSQLiteParamsBinder $b) use ($stream) {
  // Note: we're not even using $b->i here as our data stream is statefull
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
function fetch(string $sql, array $params = [], callable $row_func = null): tuple(mixed, bool)
```

* `$sql` SQL query string with optional bind var placeholders
* `$params` bind variables for the query
* `$row_func` a callback that is called for every row, its return value is collected

If `$row_func` is null, default mapping behavior is used (identity function).

When to use: execute a query once, collect results.

```php
// The simplest case: no bind params, default mapping function, collecting all results.
// The result rows are arrays of [x, y].
$query = 'SELECT x, y FROM tab';
[$rows, $ok] = $db->fetch($query);
if (!$ok) {
  handle_error($db->getLastError());
}
foreach ($query as $i => [$x, $y]) {
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
foreach ($query as $i => $data) {
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
foreach ($query as $i => $id) {
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
function query(string $sql, array $params, callable $row_func): bool
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
function queryPrepared(string $sql, callable $bind_params, callable $row_func): bool
```

* `$sql` SQL query string with optional bind var placeholders
* `$bind_params` a callback that binds variables for the query
* `$row_func` a void-result callback that is called for every row

When to use: same advantages like with `execPrepared`, but here you can collect the results.

```php
$ok = $db->queryPrepared(
  'SELECT * FROM fav_numbers WHERE num_id = :num_id',
  function (KSQLiteParamsBinder $binder) use ($ids) {
    if ($binder->i >= count($ids)) {
      return false;
    }
    $binder->bind(':num_id', $ids[$binder->i]);
    return true;
  },
  function(KSQLiteQueryContext $ctx) {
    // $ctx->row_seq is 0 for the first prepared query execution.
    // The second execution will have $row_seq=1 and so on.
    var_dump($ctx->row_seq . '=>' . $ctx->rowDataAssoc()['num_value']);
  }
);
if (!$ok) {
  handle_error($db->getLastError());
}
```
