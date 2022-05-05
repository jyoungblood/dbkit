# DB Kit

PHP functions to handle database connection and CRUD operations with PDO.


## INSTALLATION
Easy install with composer:
```
composer require hxgf/dbkit:0.1.0@dev
```
```php
use DBkit\db;
require __DIR__ . '/vendor/autoload.php';
```

## USAGE
### db::init($settings)
Initializes the database connection. Should be assigned to a global 'database' variable.
```php
$GLOBALS['database'] = db::init([
  'driver' => 'mysql', // optional, defaults to 'mysql'
  'host' => 'localhost',
  'name' => 'database_name',
  'user' => 'username',
  'password' => 'password'
]);
```

### db::insert($table, $input)
Sanitizes parameters and inserts an array of data into a specific table. Returns the `id` field of the record created.
```php
$new_id = db::insert("celestial_bodies", array(
  'name' => 'Luna',
  'classification' => 'moon',
  'comment' => 'Earth\'s moon, also commonly referred to as "the moon"'
));

echo $new_id;
```

### db::find($table, $criteria, $options)
Sanitizes parameters and retrieves a specific record(/s) from a specific table, building a query with `SELECT *`. Returns an array with the the data and total number of records.
```php
$planets = db::find("celestial_bodies", "classification = 'planet' ORDER BY title ASC LIMIT 8");

foreach ($planets['data'] as $p){
  echo $p['title'];
  echo $p['classification'];
  // etc, etc
}

echo $planets['total'];  // 8
```
`db::get()` and `db::fetch()` are also available as aliases to `db::find()`, and behave the exact same way.

Raw SQL queries can be used by sending a `raw` parameter like this:
```php
$space_objects = db::find("", "SELECT title, classification FROM celestial_bodies WHERE id IS NOT NULL", [
  'raw' => true
]);
```
\* NOTE: Be careful when writing raw queries, as none of the parameters are sanitized. It's recommended to use this with the [db::where_placeholders()](#dbwhere_placeholderscriteria) function.


### db::update($table, $input, $criteria)
Sanitizes parameters and updates an array of data for a specific record(/s).
```php
db::update("celestial_bodies", array(
  'name' => 'Mars',
  'comment' => 'Research "The Phobos Incident" -- we are not alone'
), "name='Marz'");
```

### db::delete($table, $criteria)
Sanitizes parameters and deletes a specific record(/s).
```php
db::delete("celestial_bodies", "name='venice'");
```

### db::where_placeholders($criteria)
Creates placeholders and sanitizes data for query building. This function is used to sanitize parameters for all functions.

Example usage with a raw [db::find()](#dbfindtablecriteriaoptions) query:
```php
$wd = db::db_where_placeholders("id='".$_POST['id']."'");

$space_objects = db::find("", "SELECT title, classification FROM celestial_bodies WHERE " . $wd['where'], [
  'raw' => true
]);
```



