<?php
ini_set('display_errors', 'On');
error_reporting(E_ERROR | E_WARNING | E_ALL);

echo round(microtime(true) * 1000);
try {
	// 	//$pdo = new PDO($dsn, $user, $password);　 //建立连接
	// 	// $pdo = new PDO('sqlite:yourdatabase.db');
	$db = new Sqlite("sqlite:test.db");
	echo 'Create Db ok';
	// 	// //建表
	$table = "表格";
	$db -> pdo -> exec("CREATE TABLE IF NOT EXIST `{$table}`(id integer primary key,name text)");
	echo 'Create Table {$table} ok<BR>';
	$db -> insert("{$table}", ["中" => "huangffff"]);
	$lastInsertId = $db -> lastInsertId();
	// echo "======";
	$db -> update("{$table}", ["id" => $lastInsertId, "ff5" => "555555"]);
	$rs = $db -> selectRow("{$table}", ["id" => $lastInsertId]);
	print_r($rs);
	$db -> delete("{$table}", $lastInsertId);
	$rs = $db -> select("{$table}", ["id" => $lastInsertId]);
	print_r($rs);
} catch (PDOException $e) {
	echo 'Connection failed: ' . $e -> getMessage();
	var_dump($e);
}

class Sqlite {
	public $pdo = null;
	public $auto_add_column = true;
	public $auto_timestamp = true;
	private $TableInfo = [];
	private $lastSql = null;
	// private $password;
	function __construct($dsn, $username = null, $password = null, $auto_add_column = true, $auto_timestamp = true) {
		$this -> dsn = $dsn;
		$this -> username = $username;
		$this -> password = $password;
		$this -> auto_add_column = $auto_add_column;
		$this -> auto_timestamp = $auto_timestamp;
		// set default charset as utf8
		//$this -> charset = 'utf8';
		$this -> pdo = new PDO($dsn, $username, $password);
	}

	private function str_starts_with($haystack, $needle) {
		return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
	}

	private function str_ends_with($haystack, $needle) {
		return substr_compare($haystack, $needle, -strlen($needle)) === 0;
	}

	function lastInsertId() {
		return $this -> pdo -> lastInsertId();
	}

	function lastSql() {
		return $this -> lastSql;
	}

	function close() {
		$this -> pdo = null;
	}

	function where($data, $glue = "AND") {
		$sqls = [];
		$values = [];
		foreach ($data as $key => $value) {
			if ($this -> str_starts_with($key, "_")) {//忽略下划线开头的
				continue;
			}
			if ($key == "&" || $key == "&&") {
				if (is_array($value) || $value instanceof stdClass) {
					$r = $this -> where($value, "AND");
					$sqls[] = $r["sql"];
					$values = array_merge($values, $r["values"]);
				} else {
					var_dump($value);
					die("error where");
				}
			} elseif ($key == "|" || $key == "||") {
				if (is_array($value) || $value instanceof stdClass) {
					$r = $this -> where($value, "OR");
					$sqls[] = $r["sql"];
					$values = array_merge($values, $r["values"]);
				} else {
					var_dump($value);
					die("error where");
				}
			} elseif ($this -> str_ends_with($key, "==")) {
				$key = substr($key, 0, -2);
				
				if(is_array($value)){
					$in = str_repeat("?,", count($value) - 1) . "?";
					$sqls[] = "(`{$key}` IN ({$in}))";
					$values = array_merge($values, $value);
				}else{
					$sqls[] = "(`{$key}`=?)";
					$values[] = $value;
				}
			} elseif ($this -> str_ends_with($key, "!=")) {
				$key = substr($key, 0, -2);
				$sqls[] = "(`{$key}`!=?)";
				$values[] = $value;
			} elseif ($this -> str_ends_with($key, "!@") && is_array($value)) {//NOT IN
				$key = substr($key, 0, -2);
				$in = str_repeat("?,", count($value) - 1) . "?";
				$sqls[] = "(`{$key}` NOT IN ({$in}))";
				$values = array_merge($values, $value);
			} elseif ($this -> str_ends_with($key, ">=")) {
				$key = substr($key, 0, -2);
				$sqls[] = "(`{$key}`>=?)";
				$values[] = $value;
			} elseif ($this -> str_ends_with($key, "<=")) {
				$key = substr($key, 0, -2);
				$sqls[] = "(`{$key}`<=?)";
				$values[] = $value;
			} elseif ($this -> str_ends_with($key, "=")) {
				$key = substr($key, 0, -1);
				
				if(is_array($value)){
					$in = str_repeat("?,", count($value) - 1) . "?";
					$sqls[] = "(`{$key}` IN ({$in}))";
					$values = array_merge($values, $value);
				}else{
					$sqls[] = "(`{$key}`=?)";
					$values[] = $value;
				}
			} elseif ($this -> str_ends_with($key, ">")) {
				$key = substr($key, 0, -1);
				$sqls[] = "(`{$key}`>?)";
				$values[] = $value;
			} elseif ($this -> str_ends_with($key, "<")) {
				$key = substr($key, 0, -1);
				$sqls[] = "(`{$key}`<?)";
				$values[] = $value;
			} elseif ($this -> str_ends_with($key, "@") && is_array($value)) {//IN
				$key = substr($key, 0, -1);
				$in = str_repeat("?,", count($value) - 1) . "?";
				$sqls[] = "(`{$key}` IN ({$in}))";
				$values = array_merge($values, $value);
			} else {//=
				if(is_array($value)){
					$in = str_repeat("?,", count($value) - 1) . "?";
					$sqls[] = "(`{$key}` IN ({$in}))";
					$values = array_merge($values, $value);
				}else{
					$sqls[] = "(`{$key}`=?)";
					$values[] = $value;
				}
			}

		}
		$result = [];
		$result["sql"] = "(" . join($sqls, " {$glue} ") . ")";
		$result["values"] = $values;
		return $result;
	}

	function insert($name, $data) {
		$info = $this -> table_info($name);
		if ($this -> auto_timestamp == true) {
			$data["timestamp"] = round(microtime(true) * 1000);
		}
		$pk = $info["pk"];
		$pkv = null;
		$sql = "INSERT INTO `{$name}` ";
		$params = [];
		$values = [];
		foreach ($data as $key => $value) {
			if ($this -> auto_add_column == true && !array_key_exists($key, $info["cols"])) {
				if (!$this -> add_column($name, $data)) {
					die("add_column error!");
				}
			}
			if (strpos($key, "_") !== 0) {
				$params[$key] = "?";
				$values[] = $value;
			}
		}
		$sql .= "(`" . join(array_keys($params), "`,`") . "`) VALUES (" . join(array_values($params), ",") . ")";
		echo "SQL={$sql}\n";
		$this -> lastSql = $sql;
		$sth = $this -> pdo -> prepare($sql);
		$count = $sth -> execute($values);
		if ($count < 1) {
			var_dump($this -> pdo -> errorInfo());
			echo "insert error!";
		}
		echo "affact rows={$count}\n";
		return $count;
	}

	function selectOne($name, $where, $cols = "*") {
		$result = $this -> select($name, $where, $cols, null, 1, 0);
		if (!empty($result)) {
			foreach ($result[0] as $key => $value) {
				return $value;
			}
		} else {
			return null;
		}
	}

	function selectRow($name, $where, $cols = "*", $orderby = null) {
		$result = $this -> select($name, $where, $cols, $orderby, 1, 0);
		if (!empty($result)) {
			return $result[0];
		} else {
			return null;
		}
	}

	function select($name, $where, $cols = "*", $orderby = null, $limit = 0, $offset = 0) {
		$info = $this -> table_info($name);
		$pk = $info["pk"];

		if (is_array($cols)) {
			$cols = " `" . join($cols, "`,`") . "` ";
		}
		if (empty($where)) {
			$sql = "SELECT {$cols} FROM `{$name}`";
			if (!empty($orderby)) {
				if ($orderby instanceof stdClass) {
					$o = [];
					foreach ($orderby as $key => $value) {
						$o[] = "`{$key}` {$value}";
					}
					$sql .= " ORDER BY " . join($o, ",");
					;
				} else {
					$sql .= " ORDER BY {$orderby}";
				}
			}
			if ($limit > 0) {
				$sql .= " LIMIT {$limit}";
				if ($limit > 0) {
					$sql .= " OFFSET {$offset}";
				}
			}
			echo "SQL={$sql}\n";
			$this -> lastSql = $sql;
			$sth = $this -> pdo -> prepare($sql);
			$sth -> execute();
		} else {
			if (!(is_array($where) || $where instanceof stdClass)) {
				$where = [$pk => $where];
			}
			$w = $this -> where($where);
			$sql = "SELECT {$cols} FROM `{$name}` WHERE " . $w["sql"];
			if ($limit > 0) {
				$sql .= " LIMIT {$limit}";
				if ($limit > 0) {
					$sql .= " OFFSET {$offset}";
				}
			}
			echo "SQL={$sql}\n";
			$this -> lastSql = $sql;
			$sth = $this -> pdo -> prepare($sql);
			var_dump($w);
			$sth -> execute($w["values"]);
		}
		return $sth -> fetchAll(PDO::FETCH_ASSOC);

	}

	function update($name, $data, $where = null) {
		$info = $this -> table_info($name);
		$pk = $info["pk"];

		if ($where == null) {
			if (array_key_exists($pk, $data)) {
				$where = ["{$pk}" => $data[$pk]];
			} else {
				echo "param error!";
				return false;
			}
		}
		if (!is_object($where) && !is_array($where)) {
			$where = ["{$pk}" => $where];
		}

		if ($this -> auto_timestamp == true) {
			$data["timestamp"] = round(microtime(true) * 1000);
		}
		$sql = "UPDATE `{$name}` SET ";
		$params = [];
		$values = [];
		foreach ($data as $key => $value) {
			if ($this -> auto_add_column == true && !array_key_exists($key, $info["cols"])) {
				if (!$this -> add_column($name, $data)) {
					die("add_column error!");
				}
			}
			if (strpos($key, "_") !== 0) {
				$params[] = "`{$key}`=?";
				$values[] = $value;
			}
		}
		$w = $this -> where($where);
		$sql .= join($params, ",") . " WHERE " . $w["sql"];
		$values = array_merge($values, $w["values"]);
		echo "SQL={$sql}\n";
		$this -> lastSql = $sql;
		$sth = $this -> pdo -> prepare($sql);
		$count = $sth -> execute($values);
		echo "affact rows={$count}\n";
		return $count;
	}

	function delete($name, $where) {
		$info = $this -> table_info($name);
		$pk = $info["pk"];

		if (!(is_array($where) || $where instanceof stdClass)) {
			$where = [$pk => $where];
		}
		$w = $this -> where($where);
		$sql = "DELETE FROM `{$name}` WHERE " . $w["sql"];
		echo "SQL={$sql}\n";
		$this -> lastSql = $sql;
		$sth = $this -> pdo -> prepare($sql);
		$count = $sth -> execute($w["values"]);
		return $count;
	}

	function add_column($name, $data) {//alterTable
		$info = $this -> table_info($name);

		foreach ($data as $key => $value) {
			if (strpos($key, "_") !== 0 && !array_key_exists($key, $info["cols"])) {//无此列
				$type = "TEXT";
				if (is_bool($value)) {
					$type = "INTEGER";
				} else if (is_int($value)) {
					$type = "INTEGER";
				}

				$sql = "ALTER TABLE `{$name}` ADD " . $this -> pdo -> quote($key) . " {$type};";
				echo "SQL={$sql}\n";
				$this -> lastSql = $sql;
				$result = $this -> pdo -> exec($sql);
				if ($result === false) {
					var_dump($this -> pdo -> errorInfo());
					$this -> table_info($name, true);
					return false;
				}

			}
		}
		$this -> table_info($name, true);
		return true;
	}

	function table_info($name, $force = false) {
		if ($force == false && array_key_exists($name, $this -> TableInfo)) {
			return $this -> TableInfo[$name];
		}
		$sth = $this -> pdo -> prepare("pragma table_info (`{$name}`)");
		$sth -> execute();
		//获取结果
		$info = $sth -> fetchAll();
		if (!empty($info)) {
			$cols = [];
			$pk = null;
			foreach ($info as $index => $value) {
				$cols[$value["name"]] = $value;
				if ($value["pk"] == 1) {
					$pk = $value["name"];
				}
			}
			$this -> TableInfo[$name] = ["pk" => $pk, "cols" => $cols];
		}
		return $this -> TableInfo[$name];
	}

}
