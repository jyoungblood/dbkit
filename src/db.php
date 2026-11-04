<?php

/**
 * @package    VPHP - DB Kit
 * @version    2.0.0
 * @author     Jonathan Youngblood <jy@hxgf.io>
 * @license    https://github.com/jyoungblood/dbkit/blob/master/LICENSE.md (MIT License)
 * @source     https://github.com/jyoungblood/dbkit
 */

namespace VPHP;

use PDO;
use InvalidArgumentException;
use LogicException;

class db {

  // Return a connection or throw. The application owns error reporting.
  public static function init($args){
    foreach (['host', 'name', 'user', 'password'] as $key){
      if (!isset($args[$key])){
        throw new InvalidArgumentException("Missing database setting: $key");
      }
    }
    $driver = isset($args['driver']) ? $args['driver'] : 'mysql';
    $port = isset($args['port']) ? $args['port'] : '3306';
    $charset = isset($args['charset']) ? $args['charset'] : 'utf8mb4';
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    if ($driver === 'mysql'){
      $options[PDO::ATTR_EMULATE_PREPARES] = false;
    }
    return new PDO(
      $driver . ':host=' . $args['host'] . ';dbname=' . $args['name'] . ';port=' . $port . ';charset=' . $charset,
      $args['user'],
      $args['password'],
      $options
    );
  }

  private static function connection(){
    if (!isset($GLOBALS['database']) || !($GLOBALS['database'] instanceof PDO)){
      throw new LogicException('Assign a PDO connection to $GLOBALS[\'database\'] before querying.');
    }
    $connection = $GLOBALS['database'];
    // Also enforce exception mode for connections supplied by the caller.
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $connection;
  }

  // Positional parameters are zero-based lists. Named keys can include a colon.
  private static function parameters(array $params){
    $normalized = [];
    $named = null;
    foreach ($params as $key => $value){
      $isNamed = is_string($key);
      if ($named !== null && $named !== $isNamed){
        throw new InvalidArgumentException('Do not mix positional and named parameters.');
      }
      $named = $isNamed;
      if ($isNamed){
        $key = ltrim($key, ':');
        if ($key === '' || array_key_exists($key, $normalized)){
          throw new InvalidArgumentException('Named parameter keys must be nonempty and unique.');
        }
      }elseif ($key !== count($normalized)){
        throw new InvalidArgumentException('Positional parameters must be a zero-based list.');
      }
      if ($value !== null && !is_scalar($value)){
        throw new InvalidArgumentException('Parameter values must be scalar or null.');
      }
      $normalized[$key] = $value;
    }
    return $normalized;
  }

  private static function execute($sql, array $params){
    $params = self::parameters($params);
    $statement = self::connection()->prepare($sql);
    foreach ($params as $key => $value){
      $type = PDO::PARAM_STR;
      if ($value === null){
        $type = PDO::PARAM_NULL;
      }elseif (is_bool($value)){
        $type = PDO::PARAM_BOOL;
      }elseif (is_int($value)){
        $type = PDO::PARAM_INT;
      }
      $statement->bindValue(is_int($key) ? $key + 1 : ':' . $key, $value, $type);
    }
    $statement->execute();
    return $statement;
  }

  // SQL fragments and identifiers must come from trusted application code.
  public static function find($table, $where, array $params = [], array $options = []){
    $query = !empty($options['raw']) ? $where : "SELECT * FROM $table WHERE $where";
    $data = self::execute($query, $params)->fetchAll(PDO::FETCH_ASSOC);
    return ['data' => $data, 'total' => count($data)];
  }

  public static function get($table, $where, array $params = [], array $options = []){
    return self::find($table, $where, $params, $options);
  }

  public static function fetch($table, $where, array $params = [], array $options = []){
    return self::find($table, $where, $params, $options);
  }

  // Bind values without converting zero, false, or empty strings to null.
  public static function insert($table, array $input){
    if (!$input){
      throw new InvalidArgumentException('Insert values must not be empty.');
    }
    $columns = implode(', ', array_keys($input));
    $placeholders = implode(', ', array_fill(0, count($input), '?'));
    self::execute("INSERT INTO $table ($columns) VALUES ($placeholders)", array_values($input));
    return self::connection()->lastInsertId();
  }

  public static function update($table, array $input, $where, array $params = []){
    if (!$input){
      throw new InvalidArgumentException('Update values must not be empty.');
    }
    $params = self::parameters($params);
    $named = $params && is_string(key($params));
    $assignments = [];
    if ($named){
      $index = 0;
      foreach ($input as $column => $value){
        // Avoid both supplied keys and placeholders in the developer's SQL.
        do {
          $name = '__dbkit_set_' . $index++;
        }while (array_key_exists($name, $params) || strpos($where, ':' . $name) !== false);
        $assignments[] = "$column = :$name";
        $params[$name] = $value;
      }
    }else{
      foreach ($input as $column => $value){
        $assignments[] = "$column = ?";
      }
      $params = array_merge(array_values($input), $params);
    }
    $set = implode(', ', $assignments);
    self::execute("UPDATE $table SET $set WHERE $where", $params);
    return true;
  }

  public static function delete($table, $where, array $params = []){
    self::execute("DELETE FROM $table WHERE $where", $params);
    return true;
  }
}
