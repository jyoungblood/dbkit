<?php

namespace DBkit;

use PDO;

class db {
  
	// connect to the database
	public static function init($args){
		$dbh = false;
		if ($args['host']){
      $args['driver'] = $args['driver'] ? $args['driver'] : 'mysql';
			try {
			  $dbh = new PDO($args['driver'].":host=".$args['host'].";dbname=".$args['name'], $args['user'], $args['password']);
				$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			catch(\PDOException $e) {
		    echo $e->getMessage();
			}
		}
		return $dbh;
	}

	// create db placeholders, sanitize data for query building
	public static function db_where_placeholders($where){
		$d = preg_match_all('/\'([^\"]*?)\'/', $where, $o);
		foreach ($o[0] as $ph){
			$data[] = str_replace("'","",$ph);
		}
		$o = array(
			'where' => preg_replace('/\'([^\"]*?)\'/', '?', $where),
			'data' => $data,
		);
		return $o;
	}

  // sanitize parameters and retrieve data from mysql, returning array w/ total
  public static function find($table, $where, $args = false){
    $qr = false;
    $wd = db::db_where_placeholders($where);
    try {
      $query = "SELECT * FROM $table WHERE " . $wd['where'];
      if ($args['raw']){
        $query = $wd['where'];
      }
      $a = $GLOBALS['database']->prepare($query);
      $a->execute($wd['data']);
      $a->setFetchMode(PDO::FETCH_ASSOC);
    }
    catch(\PDOException $e) {
      echo $e->getMessage();
    }
    if ($args['cache'] && function_exists("apc_store")){
      $cache_key = base64_encode($table.$where);
      if ($qr = apc_fetch($cache_key)){
      }else{
        $_args = $args;
        $_args['cache'] = false;
        $qr = db_find($table, $where, $_args);
        $cache_length = 60;
        if ($args['cache_length']){
          $cache_length = $args['cache_length'];
        }
        apc_store($cache_key, $qr, $cache_length);
      }
    }else{
      $i = 0;
      while($ad = $a->fetch()){
        $qr['data'][] = $ad;
        $i++;
      }
      if ($i > 0){
        $qr['total'] = $i;
      }
    }
    return $qr;
  }

  public static function get($table, $where, $args = false){
    return db::find($table, $where, $args);
  }

  public static function fetch($table, $where, $args = false){
    return db::find($table, $where, $args);
  }

  // sanitize parameters and insert array of data into mysql, returning the id of the record created
  public static function insert($table, $input){
    $columns = '';
    $placeholders = '';
    $total = count($input);
    $i = 1;
    foreach ($input as $key => $val){
      $columns .= $key;
      $placeholders .= ':' . $key;
      if ($val != NULL){
        $data[$key] = $val;
      }else{
        $data[$key] = NULL;
      }
      if ($total != $i){
        $columns .= ", ";
        $placeholders .= ", ";
      }
      $i++;
    }
    try {
      $a = $GLOBALS['database']->prepare("INSERT INTO $table ($columns) value ($placeholders)");
      $a->execute($data);
    }
    catch(\PDOException $e) {
      echo $e->getMessage();
    }
    $o = $GLOBALS['database']->lastInsertId();
    return $o;
  }

  // sanitize parameters and update a mysql record
  public static function update($table, $input, $where){
    $query = '';
    $total = count($input);
    $i = 1;
    foreach ($input as $key => $val){
      $query .= $key . ' = ?';
      if ($val != NULL){
        $data[] = $val;
      }else{
        $data[] = NULL;
      }
      if ($total != $i){
        $query .= ", ";
      }
      $i++;
    }
    $wd = db::db_where_placeholders($where);
    foreach ($wd['data'] as $dw){
      $data[] = $dw;
    }
    try {
      $a = $GLOBALS['database']->prepare("UPDATE $table SET $query WHERE " . $wd['where']);
      $a->execute($data);
    }
    catch(\PDOException $e) {
      echo $e->getMessage();
    }
    return true;
  }

  // sanitize parameters and delete a given mysql record
  public static function delete($table, $where){
    $wd = db::db_where_placeholders($where);
    try {
      $a = $GLOBALS['database']->prepare("DELETE FROM $table WHERE " . $wd['where']);
      $a->execute($wd['data']);
    }
    catch(\PDOException $e) {
      echo $e->getMessage();
    }
    return true;
  }

}

?>