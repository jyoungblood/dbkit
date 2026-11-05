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

  // Values stay separate from SQL until PDO binds them.
  private static function parameters(array $params){
    foreach ($params as $value){
      if ($value !== null && !is_scalar($value)){
        throw new InvalidArgumentException('Parameter values must be scalar or null.');
      }
    }
    return $params;
  }

  private static function criteria($criteria, $raw = false){
    if (is_string($criteria)){
      if (trim($criteria) === ''){
        throw new InvalidArgumentException('Criteria must not be empty.');
      }
      return [$criteria, []];
    }
    if (!is_array($criteria) || !$criteria){
      throw new InvalidArgumentException('Criteria must be SQL, a column/value map, or [SQL, ...values].');
    }
    if (array_keys($criteria) === range(0, count($criteria) - 1)){
      $sql = array_shift($criteria);
      if (!is_string($sql) || trim($sql) === ''){
        throw new InvalidArgumentException('The first tuple item must be SQL.');
      }
      return [$sql, self::parameters($criteria)];
    }
    if ($raw){
      throw new InvalidArgumentException('Raw queries require SQL or [SQL, ...values].');
    }
    $conditions = [];
    $params = [];
    foreach ($criteria as $column => $value){
      if (!is_string($column) || trim($column) === ''){
        throw new InvalidArgumentException('Criteria maps require nonempty column names.');
      }
      if ($value === null){
        $conditions[] = "$column IS NULL";
      }else{
        $conditions[] = "$column = ?";
        $params[] = $value;
      }
    }
    return [implode(' AND ', $conditions), self::parameters($params)];
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
      $statement->bindValue($key + 1, $value, $type);
    }
    $statement->execute();
    return $statement;
  }

  // SQL fragments and identifiers must come from trusted application code.
  public static function find($table, $criteria, $options = []){
    if ($options === false){
      $options = [];
    }
    if (!is_array($options)){
      throw new InvalidArgumentException('Options must be an array or false.');
    }
    $raw = !empty($options['raw']);
    [$where, $params] = self::criteria($criteria, $raw);
    $query = $raw ? $where : "SELECT * FROM $table WHERE $where";
    $data = self::execute($query, $params)->fetchAll(PDO::FETCH_ASSOC);
    return ['data' => $data, 'total' => count($data)];
  }

  public static function get($table, $criteria, $options = []){
    return self::find($table, $criteria, $options);
  }

  public static function fetch($table, $criteria, $options = []){
    return self::find($table, $criteria, $options);
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

  public static function update($table, array $input, $criteria){
    if (!$input){
      throw new InvalidArgumentException('Update values must not be empty.');
    }
    [$where, $params] = self::criteria($criteria);
    $assignments = [];
    foreach ($input as $column => $value){
      $assignments[] = "$column = ?";
    }
    $params = array_merge(array_values($input), $params);
    $set = implode(', ', $assignments);
    self::execute("UPDATE $table SET $set WHERE $where", $params);
    return true;
  }

  public static function delete($table, $criteria){
    [$where, $params] = self::criteria($criteria);
    self::execute("DELETE FROM $table WHERE $where", $params);
    return true;
  }
}
