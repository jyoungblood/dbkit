[<img src="https://raw.githubusercontent.com/jyoungblood/vphp.dev/refs/heads/master/public/vphp-logo-100.png">](https://vphp.dev)
# DB Kit

Small PHP functions for database connections and CRUD operations with PDO.

**2.0.1 changes the criteria API from 2.0.0.** This README documents 2.0.1; existing 2.0.0 callers must follow the [migration guide](#from-200).

## Installation

```sh
composer require jyoungblood/dbkit
```

```php
use VPHP\db;
require __DIR__ . '/vendor/autoload.php';
```

## Connect

```php
$GLOBALS['database'] = db::init([
  'host' => 'localhost',
  'name' => 'database_name',
  'user' => 'username',
  'password' => 'password',
  'driver' => 'mysql',    // optional
  'port' => '3306',       // optional
  'charset' => 'utf8mb4', // optional
]);
```

Returns a PDO connection. `host`, `name`, `user`, and `password` are required and cannot be null.
The DSN targets MySQL and uses native prepared statements. For other drivers, assign your own PDO connection to `$GLOBALS['database']`.
DBKit uses exception mode, including on caller-supplied connections.

## Criteria

Read, update, and delete methods accept the same three forms:

```php
// Column/value map: bound equality checks joined with AND.
['hash' => $hash, 'enabled' => true]

// Null values become IS NULL.
['deleted_at' => null]

// SQL tuple: SQL first, then one value per ? placeholder.
['visits > ? ORDER BY title LIMIT ?', $minimum, 8]

// Plain SQL: trusted application code, executed as written.
"classification = 'planet' ORDER BY title LIMIT 8"
```

Map values and tuple values are bound through PDO. Pass values without SQL escaping or extra quotes.
Do not interpolate user input into SQL strings or quote `?` placeholders. DBKit does not extract values from SQL.

Table names, column names (including map keys), and SQL fragments must come from trusted application code.
Use an allowlist for user-selected identifiers or ordering. Placeholders protect values only.

Empty criteria arrays and blank SQL are rejected. Use an explicit `'1 = 1'` when you intend to match every row.
Maps support equality and null checks; use a tuple for other operators, `OR`, sorting, or limits.
Tuples are zero-based lists with scalar or null values, and use positional `?` placeholders.
For `IN` queries, provide one placeholder per value: `['id IN (?, ?)', $firstId, $secondId]`.
In SQL tuples, use `IS NULL` for null comparisons rather than `= ?` with a null value.

## Read

```php
$result = db::find('urls', ['hash' => $hash]);

$result = db::find('celestial_bodies', [
  'classification = ? ORDER BY title LIMIT ?', 'planet', 8,
]);

foreach ($result['data'] as $row) {
  echo $row['title'];
}
echo $result['total'];
```

`db::get()` and `db::fetch()` are identical aliases. All three use `($table, $criteria, $options = [])`.
The standard query is `SELECT * FROM $table WHERE ...`.

Returns `['data' => $rows, 'total' => count($rows)]`, with associative rows.
No matches returns `['data' => [], 'total' => 0]`. `total` counts returned rows after any SQL limit.

### Raw queries

Options stay in the third argument. `raw` is the only supported option.

```php
$result = db::find('', ['SELECT title FROM urls WHERE hash = ?', $hash], [
  'raw' => true,
]);

$result = db::get('', 'SELECT COUNT(*) AS total FROM urls', ['raw' => true]);
```

A truthy `raw` runs the criteria as the complete SQL query and ignores `$table`.
Use a SQL string or tuple for raw queries; column/value maps are not supported.
`['raw' => false]` uses the standard SELECT query. Legacy `false` options are also accepted.

## Insert

```php
$id = db::insert('urls', [
  'hash' => $hash,
  'title' => $title,
  'visits' => 0,
  'deleted_at' => null,
]);
```

Every value is bound. Input must be a nonempty associative array of trusted column names and values.
Returns PDO's `lastInsertId()` after success, normally a string. ID behavior depends on the driver and schema.

## Update

```php
db::update('urls', ['title' => $title], ['hash' => $hash]);

db::update('urls', ['enabled' => false], ['visits < ?', $minimum]);
```

Uses `($table, $input, $criteria)`. Input must be nonempty. Update and criteria values are bound separately.
Returns `true` after successful execution, including unchanged values or zero matching rows.

## Delete

```php
db::delete('urls', ['hash' => $hash]);

db::delete('urls', ['visits < ? AND enabled = ?', $minimum, false]);
```

Uses `($table, $criteria)`. Returns `true` after successful execution, including zero matching rows.

## Values and errors

Integers, booleans, and nulls use their matching PDO parameter types. Strings and floats use `PDO::PARAM_STR`
(PDO has no float parameter type). The database applies its column conversion rules.
Zero, `'0'`, `false`, and empty strings remain values; use explicit `null` for SQL NULL.
Arrays, objects, and resources cannot be bound as individual values.

Connection and query failures throw exceptions without printing errors or returning success after failure.
Invalid criteria, unsupported values, empty insert/update input, and missing connection settings throw `InvalidArgumentException`.
A missing or invalid global PDO connection throws `LogicException`.
The calling application decides how to catch, log, and display errors.

## Migration to 2.0.1

### From 1.x

The original method names, argument positions, and successful return values remain.
Trusted SQL strings still work. Replace interpolated values with maps or tuples:

```php
// Before: unsafe interpolation.
$result = db::find('urls', "hash = '$hash'");

// After: automatically bound.
$result = db::find('urls', ['hash' => $hash]);

// Before: unsafe interpolation.
db::update('urls', ['title' => $title], "hash = '$hash'");
db::delete('urls', "hash = '$hash'");

// After.
db::update('urls', ['title' => $title], ['hash' => $hash]);
db::delete('urls', ['hash' => $hash]);
```

`create_placeholders()` is removed; there is no regex extraction or automatic sanitization of interpolated SQL.
`init()` and failed queries now throw instead of returning false or printing errors. Zero, false, and empty strings no longer become null.
The `raw` option now requires a truthy value.

### From 2.0.0

The separate-parameters API from 2.0.0 is no longer supported. Move SQL and values into one tuple, or use a column/value map. Move read options back to argument three. These changes also apply to `get()` and `fetch()`.

```php
// Earlier API.
$result = db::find('', 'SELECT title FROM urls WHERE hash = ?', [$hash], ['raw' => true]);

// Current API.
$result = db::find('', ['SELECT title FROM urls WHERE hash = ?', $hash], ['raw' => true]);

// Earlier API.
$result = db::find('urls', 'hash = :hash', ['hash' => $hash]);
db::update('urls', ['title' => $title], 'hash = ?', [$hash]);
db::delete('urls', 'hash = ?', [$hash]);

// Current API.
$result = db::find('urls', ['hash' => $hash]);
db::update('urls', ['title' => $title], ['hash' => $hash]);
db::delete('urls', ['hash' => $hash]);
```

Replace named bindings with maps or positional tuples. `init()`, `insert()`, and successful return shapes are unchanged from 2.0.0.

Version `2.0.0` retains its original API. After migrating callers, update your Composer constraint if pinned to `2.0.0`, then run `composer update jyoungblood/dbkit` to install `2.0.1`.

## Direct PDO access

`$GLOBALS['database']` is a PDO object. Use it directly for transactions or driver-specific operations.
Keep SQL and parameter values separate in direct PDO queries too.
