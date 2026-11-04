[<img src="https://raw.githubusercontent.com/jyoungblood/vphp.dev/refs/heads/master/public/vphp-logo-100.png">](https://vphp.dev)
# DB Kit

PHP functions for database connections and CRUD operations with PDO.

## Breaking changes in 2.0.0

**Migrate callers before you upgrade to 2.0.0.**

- `find()`, `get()`, and `fetch()` now take parameters as their third argument and options as their fourth argument.
- `update()` and `delete()` now take WHERE parameters separately from SQL and update values.
- `create_placeholders()` is removed. There is no regex extraction or compatibility fallback.
- SQL strings run as written. Legacy interpolated calls do **not** become safe automatically.
- Connection and query failures throw exceptions. DBKit no longer prints database errors or returns success after failure.
- `init()` throws for missing required settings instead of returning `false` for a missing host.
- Insert and update values retain `0`, `'0'`, `false`, and `''`. These values no longer become `NULL`.
- Parameter arrays must contain scalar values or `null`. Empty insert/update arrays are rejected.
- `raw` now requires a truthy value. `['raw' => false]` uses the standard SELECT query.

See [Migration](#migration) for before/after examples of every affected method.

## Installation

```sh
composer require jyoungblood/dbkit
```

```php
use VPHP\db;
require __DIR__ . '/vendor/autoload.php';
```

## Binding rules

Keep SQL and parameter values separate. Do not interpolate user input into SQL or quote placeholders.

- Positional parameters use `?` and a zero-based list, such as `[$hash, $id]`.
- Named parameters use `:name` and an associative array, such as `['hash' => $hash]`. Keys can also include the colon.
- Use one parameter style per query. Use a distinct named placeholder for each occurrence for portability across PDO drivers.
- Integers, booleans, and nulls use their matching PDO parameter types. Strings use `PDO::PARAM_STR`.
- Floats use `PDO::PARAM_STR` because PDO has no float parameter type. The database applies its column conversion rules.
- An array cannot bind to one placeholder. For an `IN` list, generate one placeholder per value and pass each value separately.

Placeholders protect **values only**. Table names, column names, WHERE clauses, ordering, and raw SQL remain developer-controlled.
For user-selected identifiers or SQL fragments, choose from a fixed allowlist in application code.
SQL `NULL` comparisons follow database rules. Use `IS NULL` to select null values, rather than `= ?` with a null parameter.

## API

### `db::init($settings)`

Returns a PDO connection. Assign it to `$GLOBALS['database']` before you call other methods.

```php
$GLOBALS['database'] = db::init([
  'host' => 'localhost',
  'name' => 'database_name',
  'user' => 'username',
  'password' => 'password',
  'driver' => 'mysql', // default
  'port' => '3306', // default
  'charset' => 'utf8mb4', // default
]);
```

The connection uses `PDO::ERRMODE_EXCEPTION`. MySQL connections from `init()` also use native prepared statements.
The DSN format targets MySQL. For other drivers, create a PDO connection directly and assign it to `$GLOBALS['database']`.
DBKit also sets exception mode before queries on caller-supplied PDO connections.

Required settings are `host`, `name`, `user`, and `password`. Missing or null settings throw `InvalidArgumentException`.
Connection failures propagate the original `PDOException`.

### Read methods

```php
db::find($table, $where, $params = [], $options = []);
db::get($table, $where, $params = [], $options = []);
db::fetch($table, $where, $params = [], $options = []);
```

All three methods behave identically. The standard query is `SELECT * FROM $table WHERE $where`.
The `$params` and `$options` arguments must be arrays.

```php
// Standard query
$result = db::find('urls', 'hash = ?', [$hash]);

// Named parameter
$result = db::find('urls', 'hash = :hash', ['hash' => $hash]);

// Raw query
$result = db::find('', 'SELECT * FROM urls WHERE hash = ?', [$hash], [
  'raw' => true
]);

// Aliases accept the same parameters and options.
$result = db::get('urls', 'hash = ?', [$hash]);
$result = db::fetch('urls', 'hash = :hash', ['hash' => $hash]);

// SQL fragments stay in trusted application code.
$planets = db::find('celestial_bodies', 'classification = ? ORDER BY name ASC LIMIT 8', ['planet']);
```

`['raw' => true]` runs `$where` as the complete query and ignores `$table`. Named parameters also work with raw queries.
The only supported option is `raw`. Use raw SELECT queries when you need custom projections, joins, or aggregates.

Each method returns `['data' => $rows, 'total' => count($rows)]`. Each row is an associative array.
Successful empty queries return `['data' => [], 'total' => 0]`.
`total` counts returned rows, not rows before a SQL `LIMIT`.
Query or fetch failures throw. These methods do not return partial results after failure.

### `db::insert($table, $input)`

Inserts an associative array of column names and values. Column names must come from trusted application code.
Every value uses a bound parameter. `$input` must be a nonempty array.

```php
$newId = db::insert('urls', [
  'hash' => $hash,
  'title' => $title,
  'visit_count' => 0,
  'comment' => null,
]);
```

Returns PDO's `lastInsertId()` result after a successful insert. This is normally a string.
ID behavior depends on the database driver and schema. DBKit does not promise an ID for tables without generated IDs.
Failures throw before DBKit retrieves an ID. Empty input throws `InvalidArgumentException`.

### `db::update($table, $input, $where, $params = [])`

`$input` contains column names and new values. `$params` contains only WHERE parameter values.
Both arguments must be arrays. `$input` must be nonempty. There is no options argument for update.

```php
db::update('urls', ['title' => $title], 'hash = ?', [$hash]);

db::update('urls', ['hash' => $newHash], 'hash = :hash', ['hash' => $oldHash]);
```

DBKit binds SET values separately from WHERE values. Generated named placeholders avoid collisions with WHERE parameter names.
For positional parameters, DBKit places SET values before WHERE values internally.

Returns `true` after successful execution, including zero matching rows or unchanged values.
This return value confirms execution, not a changed row count. Failures throw. Empty input throws `InvalidArgumentException`.

### `db::delete($table, $where, $params = [])`

`$params` is an array of WHERE parameter values. There is no options argument for delete.

```php
db::delete('urls', 'hash = ?', [$hash]);
db::delete('urls', 'hash = :hash', ['hash' => $hash]);
```

Returns `true` after successful execution, including zero matching rows. Failures throw.

## Errors

DBKit produces no direct error output, HTTP response, or application log entry.
PDO exceptions propagate unchanged. DBKit does not catch them or continue the failed operation.
The calling application decides where to catch exceptions, what to log, and what to show users.

Invalid parameter arrays, unsupported value types, and empty insert/update input throw `InvalidArgumentException`.
Missing or invalid global PDO connections throw `LogicException`.
Non-array arguments for array parameters produce PHP type errors.
Successful queries with zero results or zero affected rows are not failures.

## Migration

### `find()`, `get()`, and `fetch()`

Replace interpolated values with placeholders. Move values into the third argument.

```php
// Before: unsafe legacy calls. Do not copy into new code.
$result = db::find('urls', "hash = '$hash'");
$result = db::get('urls', "hash = '$hash'");
$result = db::fetch('urls', "hash = '$hash'");

// After
$result = db::find('urls', 'hash = ?', [$hash]);
$result = db::get('urls', 'hash = ?', [$hash]);
$result = db::fetch('urls', 'hash = :hash', ['hash' => $hash]);
```

Move old third-argument options to the **fourth argument**. Use `[]` for parameters when the SQL needs no values.
This change applies to all three read methods.

```php
// Before: unsafe legacy raw query.
$result = db::find('', "SELECT * FROM urls WHERE hash = '$hash'", ['raw' => true]);

// After
$result = db::find('', 'SELECT * FROM urls WHERE hash = ?', [$hash], ['raw' => true]);

// Before: raw query without parameters.
$result = db::get('', 'SELECT * FROM urls', ['raw' => true]);

// After: the empty parameter array is required before options.
$result = db::get('', 'SELECT * FROM urls', [], ['raw' => true]);
$result = db::fetch('', 'SELECT * FROM urls', [], ['raw' => true]);
```

Remove old `false` arguments for options, or replace them with arrays in the correct positions.

### `update()` and `delete()`

Keep update values in `$input`. Pass WHERE values in their own parameter array.

```php
// Before: unsafe legacy predicates.
db::update('urls', ['hash' => $newHash], "hash = '$oldHash'");
db::delete('urls', "hash = '$hash'");

// After
db::update('urls', ['hash' => $newHash], 'hash = :hash', ['hash' => $oldHash]);
db::delete('urls', 'hash = ?', [$hash]);
```

The successful return value remains `true`. Failed writes now throw instead of printing errors and returning `true`.

### `insert()`

The signature stays the same. Pass values without SQL escaping or added SQL quotes.

```php
// Before and after: values are already separate from SQL.
$id = db::insert('urls', ['hash' => $hash, 'visit_count' => 0, 'comment' => null]);
```

Zero, false, and empty-string values now remain values. Use explicit `null` where your schema needs SQL `NULL`.
Failed inserts now throw instead of printing errors and returning a stale ID.

### `init()` and error handling

The successful connection assignment stays the same. Replace failure checks with application-level exception handling.

```php
// Before: false checks do not handle the new throwing behavior.
$connection = db::init($settings);
if (!$connection) {
  // Application failure path.
}

// After: use the application's error boundary.
try {
  $GLOBALS['database'] = db::init($settings);
  $result = db::find('urls', 'hash = ?', [$hash]);
} catch (\PDOException | \InvalidArgumentException $error) {
  // The application decides how to log and present the failure.
  // Rethrow here if an outer application boundary handles it.
  throw $error;
}
```

Apply the same error boundary to insert, update, delete, and raw queries.
Do not rely on printed errors, `false`, or a success value after a failed operation.

### `create_placeholders()`

Remove calls to this method. There is no replacement that parses already-built SQL safely.

```php
// Before: unsafe SQL construction and removed helper.
$parts = db::create_placeholders("hash = '$hash'");
$statement = $GLOBALS['database']->prepare('SELECT * FROM urls WHERE ' . $parts['where']);
$statement->execute($parts['data']);

// After: values remain separate from SQL from the start.
$statement = $GLOBALS['database']->prepare('SELECT * FROM urls WHERE hash = ?');
$statement->execute([$hash]);
```

## Direct PDO access

`$GLOBALS['database']` is a PDO object. Native PDO methods remain available for transactions and driver-specific operations.
Use prepared statements and separate parameter values for direct queries too.

## Tests

The regression suite needs PHP with PDO and `pdo_sqlite`. It creates a fresh in-memory database for each test.
It does not access application databases or need Composer dependencies.

```sh
php tests/regression.php
# Or:
composer test
```

Tests cover injection strings, value types, aliases, raw SQL, named-binding collisions, exceptions without output, and successful empty operations.
The local suite uses SQLite. It does not replace integration tests against your production database driver.
