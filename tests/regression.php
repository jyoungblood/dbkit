<?php

require __DIR__ . '/../src/db.php';

use VPHP\db;

// No Composer dependencies or external database required.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)){
  fwrite(STDERR, "The regression suite requires pdo_sqlite.\n");
  exit(1);
}

set_error_handler(function ($severity, $message, $file, $line){
  throw new ErrorException($message, 0, $severity, $file, $line);
});

function same($expected, $actual){
  if ($expected !== $actual){
    throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

function throwsQuietly($class, callable $operation){
  $caught = null;
  $returned = false;
  ob_start();
  try {
    $operation();
    $returned = true;
  }catch (Throwable $error){
    $caught = $error;
  }finally {
    $output = ob_get_clean();
  }
  same('', $output);
  same(false, $returned);
  if (!($caught instanceof $class)){
    throw new RuntimeException('Expected ' . $class . ', got ' . ($caught ? get_class($caught) . ': ' . $caught->getMessage() : 'no exception'));
  }
  return $caught;
}

function fixture(){
  $connection = new PDO('sqlite::memory:');
  $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $connection->exec('CREATE TABLE urls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hash TEXT NOT NULL UNIQUE,
    title TEXT,
    quantity INTEGER,
    amount REAL,
    enabled INTEGER
  )');
  $GLOBALS['database'] = $connection;
  db::insert('urls', ['hash' => 'first', 'title' => 'First']);
  db::insert('urls', ['hash' => 'second', 'title' => 'Second']);
  return $connection;
}

$tests = [];

foreach (['find', 'get', 'fetch'] as $method){
  $tests["$method binds positional and named values"] = function () use ($method){
    $payload = "' OR 1=1 --";
    same(0, db::$method('urls', 'hash = ?', [$payload])['total']);
    db::insert('urls', ['hash' => $payload]);
    foreach ([['hash = ?', [$payload]], ['hash = :hash', ['hash' => $payload]], ['hash = :hash', [':hash' => $payload]]] as $query){
      $result = db::$method('urls', $query[0], $query[1]);
      same(1, $result['total']);
      same($payload, $result['data'][0]['hash']);
    }
  };

  $tests["$method supports raw SQL and fourth-argument options"] = function () use ($method){
    foreach ([['?', ["' OR 1=1 --"]], [':hash', ['hash' => "' OR 1=1 --"]]] as $query){
      same(['data' => [], 'total' => 0], db::$method('', 'SELECT * FROM urls WHERE hash = ' . $query[0], $query[1], ['raw' => true]));
    }
    same('first', db::$method('', 'SELECT hash FROM urls WHERE id = ?', [1], ['raw' => true])['data'][0]['hash']);
    same('second', db::$method('', 'SELECT hash FROM urls WHERE id = :id', ['id' => 2], ['raw' => true])['data'][0]['hash']);
    same(2, db::$method('', 'SELECT * FROM urls', [], ['raw' => true])['total']);
    same(1, db::$method('urls', 'id = ?', [1], ['raw' => false])['total']);
  };

  $tests["$method preserves empty results"] = function () use ($method){
    same(['data' => [], 'total' => 0], db::$method('urls', 'hash = ?', ['missing']));
  };

  $tests["$method throws quietly on prepare and execute errors"] = function () use ($method){
    throwsQuietly(PDOException::class, function () use ($method){
      db::$method('missing_table', 'id = ?', [1]);
    });
    throwsQuietly(PDOException::class, function () use ($method){
      db::$method('urls', 'id = :id', ['wrong_parameter' => 1]);
    });
    throwsQuietly(PDOException::class, function () use ($method){
      db::$method('', 'INVALID SQL', [], ['raw' => true]);
    });
  };
}

$tests['insert preserves quotes, Unicode, nulls, and numbers'] = function (){
  $values = [
    'hash' => "O'Reilly \"quoted\" \\ 雪 🌙",
    'title' => null,
    'quantity' => 0,
    'amount' => 12.5,
    'enabled' => false,
  ];
  $id = db::insert('urls', $values);
  same('3', $id);
  $row = db::find('urls', 'id = ?', [(int) $id])['data'][0];
  foreach ($values as $key => $value){
    same($key === 'enabled' ? 0 : $value, $row[$key]);
  }
  same(1, db::find('urls', 'title IS NULL AND quantity = ? AND amount = ?', [0, 12.5])['total']);
  same(1, db::find('urls', 'hash = :hash AND title IS :title', ['hash' => $values['hash'], 'title' => null])['total']);
  db::insert('urls', ['hash' => '0', 'title' => '', 'quantity' => -42, 'enabled' => true]);
  $row = db::find('urls', 'hash = ?', ['0'])['data'][0];
  same('0', $row['hash']);
  same('', $row['title']);
  same(-42, $row['quantity']);
  same(1, $row['enabled']);
};

$tests['update preserves empty strings, zero, booleans, nulls, and Unicode'] = function (){
  same(true, db::update('urls', ['title' => '', 'quantity' => 0, 'enabled' => false, 'amount' => null], 'hash = ?', ['first']));
  $row = db::get('urls', 'hash = ?', ['first'])['data'][0];
  same('', $row['title']);
  same(0, $row['quantity']);
  same(0, $row['enabled']);
  same(null, $row['amount']);
  same(true, db::update('urls', ['title' => "雪 'quotes' 🌙", 'quantity' => -42, 'enabled' => true, 'amount' => 0.5], 'hash = :hash', ['hash' => 'first']));
  $row = db::get('urls', 'hash = ?', ['first'])['data'][0];
  same("雪 'quotes' 🌙", $row['title']);
  same(-42, $row['quantity']);
  same(1, $row['enabled']);
  same(0.5, $row['amount']);
  db::update('urls', ['title' => null], 'hash = ?', ['first']);
  same(1, db::find('urls', 'title IS :title', ['title' => null])['total']);
};

$tests['typed parameters work in predicates and raw expressions'] = function (){
  $result = db::find('', 'SELECT ? AS missing, ? AS count, ? AS enabled, ? AS amount, ? AS text', [null, 0, false, 1.25, '0'], ['raw' => true]);
  same(['missing' => null, 'count' => 0, 'enabled' => 0, 'amount' => '1.25', 'text' => '0'], $result['data'][0]);
  same(1, db::find('urls', 'id > ? ORDER BY id LIMIT ?', [0, 1])['total']);
  same(2, db::find('urls', 'quantity IS :quantity', ['quantity' => null])['total']);
};

$tests['developer SQL literals stay intact without regex extraction'] = function (){
  $result = db::find('', "SELECT 'O''Reilly' AS literal, :value AS bound", ['value' => "雪 ' OR 1=1 --"], ['raw' => true]);
  same(['literal' => "O'Reilly", 'bound' => "雪 ' OR 1=1 --"], $result['data'][0]);
};

$tests['fetch failures preserve the exception and return no partial results'] = function (){
  db::insert('urls', ['hash' => 'third']);
  foreach (['find', 'get', 'fetch'] as $method){
    $seen = [];
    $failure = new PDOException('Failure while fetching the second row');
    $GLOBALS['database']->sqliteCreateFunction('fail_on_second', function ($id) use (&$seen, $failure){
      $seen[] = $id;
      if ($id === 2){
        throw $failure;
      }
      return $id;
    }, 1);
    $caught = throwsQuietly(PDOException::class, function () use ($method){
      db::$method('', 'SELECT fail_on_second(id) FROM urls ORDER BY id', [], ['raw' => true]);
    });
    same($failure, $caught);
    same([1, 2], $seen);
  }
};

foreach (['positional', 'named'] as $style){
  $tests["$style update and delete predicates resist injection"] = function () use ($style){
    $payload = "' OR 1=1 --";
    $where = $style === 'named' ? 'hash = :hash' : 'hash = ?';
    $params = $style === 'named' ? ['hash' => $payload] : [$payload];
    same(true, db::update('urls', ['title' => 'Changed'], $where, $params));
    same(true, db::delete('urls', $where, $params));
    same(['First', 'Second'], array_column(db::find('urls', '1 = 1 ORDER BY id')['data'], 'title'));
    db::insert('urls', ['hash' => $payload]);
    same(true, db::update('urls', ['title' => $payload], $where, $params));
    same($payload, db::find('urls', $where, $params)['data'][0]['title']);
    same(true, db::delete('urls', $where, $params));
    same(2, db::find('urls', '1 = 1')['total']);
  };
}

$tests['update bindings do not collide with columns or generated-looking names'] = function (){
  same(true, db::update('urls', ['hash' => 'renamed', 'title' => 'Changed'],
    'hash = :hash AND id = :__dbkit_set_0 AND title = :__dbkit_set_1',
    [':hash' => 'first', '__dbkit_set_0' => 1, ':__dbkit_set_1' => 'First']));
  same('Changed', db::find('urls', 'hash = ?', ['renamed'])['data'][0]['title']);
  same('Second', db::find('urls', 'hash = ?', ['second'])['data'][0]['title']);
  same(true, db::update('urls', ['hash' => 'again', 'title' => 'Updated'], 'hash = ? AND id = ?', ['renamed', 1]));
  same('Updated', db::find('urls', 'hash = ?', ['again'])['data'][0]['title']);
};

$tests['successful zero-row and unchanged writes return true'] = function (){
  same(true, db::update('urls', ['title' => 'First'], 'hash = ?', ['first']));
  same(true, db::update('urls', ['title' => 'Ignored'], 'hash = :hash', ['hash' => 'missing']));
  same(true, db::delete('urls', 'hash = ?', ['missing']));
  same(true, db::update('urls', ['title' => 'Ignored'], '1 = 0'));
  same(true, db::delete('urls', '1 = 0'));
  same(2, db::find('urls', '1 = 1')['total']);
};

$tests['failed writes throw without returning success or a stale insert ID'] = function (){
  throwsQuietly(PDOException::class, function (){ db::insert('urls', ['hash' => 'first']); });
  throwsQuietly(PDOException::class, function (){ db::insert('missing_table', ['hash' => 'third']); });
  throwsQuietly(PDOException::class, function (){ db::update('urls', ['hash' => 'second'], 'hash = ?', ['first']); });
  throwsQuietly(PDOException::class, function (){ db::update('missing_table', ['title' => 'x'], 'id = ?', [1]); });
  throwsQuietly(PDOException::class, function (){ db::delete('missing_table', 'id = ?', [1]); });
  $GLOBALS['database']->exec("CREATE TRIGGER prevent_delete BEFORE DELETE ON urls BEGIN SELECT RAISE(ABORT, 'delete blocked'); END");
  throwsQuietly(PDOException::class, function (){ db::delete('urls', 'hash = ?', ['first']); });
  same(2, db::find('urls', '1 = 1')['total']);
};

$tests['connection failures and missing settings throw quietly'] = function (){
  throwsQuietly(PDOException::class, function (){
    db::init(['driver' => 'dbkit_nonexistent_driver', 'host' => 'localhost', 'name' => 'dbkit', 'user' => '', 'password' => '']);
  });
  foreach (['host', 'name', 'user', 'password'] as $key){
    $settings = ['host' => 'localhost', 'name' => 'dbkit', 'user' => '', 'password' => ''];
    unset($settings[$key]);
    throwsQuietly(InvalidArgumentException::class, function () use ($settings){ db::init($settings); });
  }
  unset($GLOBALS['database']);
  throwsQuietly(LogicException::class, function (){ db::find('urls', '1 = 1'); });
};

$tests['caller-supplied silent or warning connections use exception mode'] = function (){
  foreach ([PDO::ERRMODE_SILENT, PDO::ERRMODE_WARNING] as $mode){
    $GLOBALS['database']->setAttribute(PDO::ATTR_ERRMODE, $mode);
    throwsQuietly(PDOException::class, function (){ db::delete('missing_table', '1 = 1'); });
    same(PDO::ERRMODE_EXCEPTION, $GLOBALS['database']->getAttribute(PDO::ATTR_ERRMODE));
  }
};

$tests['invalid parameter arrays and empty writes throw quietly'] = function (){
  foreach ([[1 => 'first'], [0 => 'first', 'hash' => 'first'], ['hash' => 'first', ':hash' => 'second'], ['' => 'first'], [['nested']]] as $params){
    throwsQuietly(InvalidArgumentException::class, function () use ($params){ db::find('urls', 'hash = ?', $params); });
  }
  throwsQuietly(InvalidArgumentException::class, function (){ db::insert('urls', []); });
  throwsQuietly(InvalidArgumentException::class, function (){ db::update('urls', [], '1 = 1'); });
  same(false, method_exists(db::class, 'create_placeholders'));
};

$failures = 0;
foreach ($tests as $name => $test){
  try {
    fixture();
    $test();
    echo "PASS $name\n";
  }catch (Throwable $error){
    $failures++;
    fwrite(STDERR, "FAIL $name: " . $error->getMessage() . "\n");
  }
}
echo count($tests) . " tests, $failures failures\n";
exit($failures ? 1 : 0);
