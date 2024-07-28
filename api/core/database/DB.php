<?php

class DB
{
	/**
	 * Singleton DB manipulator.
	 */

	private static $instance;
	protected $db;

	public function __construct()
	{
		if (DB_MODE == SQLITE) {
			$this->db = new PDO('sqlite:' . $_ENV['DB_PATH']);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->db->exec('PRAGMA foreign_keys = ON;');
		} else if (DB_MODE == MYSQL) {
			$this->db = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} else
			throw new PDOException("Bad Configuration", 11);
	}

	public static function getDB(): DB
	{
		if (self::$instance === null)
			self::$instance = new self();

		return self::$instance;
	}

	private static function joinToString(array $array, string $conjunction, string $relation)
	{
		/**
		 * Converts a key-value array into string with specified conjunctions
		 * You can use multiple conjunctions at different places if you put them inside the key-value array as 'conjunction'=>'anything'
		 * @param $array: key-value array
		 * @param $level1_conjunction: The default conjunction to be used everywhere unless specific conjunction is found in the passed array
		 * @param $level2_conjunction: The default relation to be used everywhere unless specific conjunction is found in the passed array
		 * @return : Formatted string in the form "key1<relation>value1 <conjunction> key2<relation>value2" and so on...
		 * 
		 * @example for defaults(conjunction=', ', relation='='): joinToString(array('school'=>'engineering university', 'age'=>'20'), ', ', '=')
		 * returns 'school=?, password=?'
		 * 
		 * @example for overriding defaults(conjunction='and' , relation='>'): joinToString(array('age'=>'40', 'balance'=>'1000000', 'years_of_experience'=>'10'), 'and', '>')
		 * returns 'age > 40 and balance > 1000000 and years_of_experience > 10'
		 * 
		 * @example for adding one-time conjunctions and relations: joinToString(array('username'=>'blah', 'or', 'age'=>'20'), ', ', '=')
		 * returns 'username=? or age<=?'
		 */
		if ($conjunction != ', ')
			$conjunction = ' ' . trim($conjunction) . ' ';

		$result = '';
		$skip_conj = false;
		foreach ($array as $key => $value) {
			if (is_numeric($key)) {
				$result .= ' ' . $value . ' ';
				$skip_conj = true;
			} else {
				// Add conjunction if previous key-value exists and not skipped
				if (strlen($result) && !$skip_conj)
					$result .= $conjunction;

				$skip_conj = false;

				// in array
				if (is_array($value) && count($value)) { // Special Case
					$result .= $key . ' in (';
					foreach ($value as $i => $possibility) {
						if ($i != count($value) - 1)
							$result .= '?, ';
						else $result .= '?)';
					}
				} else $result .= $key . $relation . '?';
			}
		}
		return $result;
	}

	private static function extractParams(array $array)
	{
		$result = array();
		foreach ($array as $key => $value) {
			if (!is_numeric($key)) {
				if (is_array($value)) array_push($result, ...$value);
				else array_push($result, $value);
			}
		}

		return $result;
	}

	public function secQuery(string $query, array $params): array
	{
		/**
		 * Generic secure query function
		 * @param $query: sql query
		 * @param $params: Desired array of parameters for binding
		 * @return array containing query status and statement handle for further manipulation
		 */
		if (DEBUG) {
			echo $query . "\n";
			var_dump($params);
			echo "\n";
		}

		$stmt = $this->db->prepare($query);

		try {
			$result = $stmt->execute($params);
		} catch (PDOException $e) {
			if (str_contains($e->getMessage(), "FOREIGN"))
				throw new ForeignKeyViolated($e->getMessage());

			else if ($this->isDuplicateViolated($e->getMessage())) {
				$conflicts = $this->getConflicts($e->getMessage());

				throw new UniquenessViolated($conflicts);
			}

			throw $e;
			$result = False;
		}

		return array($result, $stmt);
	}

	private function isDuplicateViolated(string $err): bool
	{
		return str_contains($err, "UNIQUE") || str_contains($err, "Duplicate entry");
	}

	private function getConflicts(string $err): mixed
	{
		if (DB_MODE == SQLITE) {
			$conflicts = explode(',', explode("failed: ", $err)[1]);
			foreach ($conflicts as &$conflict)
				$conflict = explode('.', $conflict)[1];
			$conflicts = implode(', ', $conflicts);
		} else {
			preg_match("/for key '(.*?)'/", $err, $matches);
			return $matches[1];
		}

		return $conflicts;
	}

	public function select($fields, string $table, array $conditions = null): array
	{
		/**
		 * Secure and easy-to-use select query
		 * @param array|string $fields: fields to fetch from each matched record
		 * @param string $table: database table to select from
		 * @param array $conditions: Matching conditions key-value pairs
		 * @return array of all matched records
		 */

		if (is_array($fields))
			$query = "select " . implode(',', $fields) . " from $table";
		else
			$query = "select $fields from $table";

		$bind_params = [];

		if (is_array($conditions) && count($conditions)) { // 
			$bind_params = DB::extractParams($conditions);
			$query .= ' where ' . DB::joinToString($conditions, 'and', "=");
		}
		return $this->secQuery($query, $bind_params)[1]->fetchAll(PDO::FETCH_ASSOC);
	}

	public function insert(string $table, array $fields, array $values = null)
	{
		/**
		 * Secure and easy-to-use insert query
		 * @param String $table: database table to select from
		 * @param array $fields: columns to be filled or column=>value key-value pairs
		 * @param array|null $values: values to be put at each column or null in case key-value pairs are used in the second parameter
		 * @return : id of the inserted record
		 * @return : false in case insert failed (uniqueness violated or not null condition violated etc...)
		 */

		if (!$values) {
			$values = array_values($fields);
			$fields = array_keys($fields);
		}

		$q_marks = '';
		$len = count($values);
		for ($i = 0; $i < $len; $i++) {
			if (strlen($q_marks))
				$q_marks .= ', ';
			$q_marks .= '?';
		}
		$query = 'insert into ' . $table . ' (' . implode(',', $fields) . ') values (' . $q_marks . ')';
		if (!is_array($values)) var_dump($values);
		if ($this->secQuery($query, $values)[0])
			return $this->db->lastInsertId();
		else return false;
	}

	public function update(string $table, array $new_values, array $conditions): int
	{
		/**
		 * Secure and easy-to-use update query
		 * @param $table: database table to select from
		 * @param $new_values: the new column-value combinations
		 * @param $conditions: Matching conditions key-value pairs
		 * @return : number of affected rows
		 * @return : false in case insert failed (uniqueness violated or not null condition violated etc...)
		 */


		$new_values_str = DB::joinToString($new_values, ', ', '=');
		$conditions_str = DB::joinToString($conditions, 'and', '=');

		$query = "update $table set $new_values_str where $conditions_str";

		$values = array_values($new_values);
		foreach ($conditions as $key => $value)
			if (!is_numeric($key))
				array_push($values, $value);

		$result = $this->secQuery($query, $values);
		if ($result[0])
			return $result[1]->rowCount();
		else return false;
	}

	public function delete(string $table, array $conditions)
	{
		/**
		 * Secure and easy-to-use delete query
		 * @param $table: database table to delete from
		 * @param $conditions: Matching conditions key-value pairs
		 * @return : number of affected rows
		 * @return : false in case delete failed
		 */

		$cond_str = ' where ' . DB::joinToString($conditions, 'and', '=');
		$query = 'delete from ' . $table . $cond_str;
		$params = DB::extractParams($conditions);
		$result = $this->secQuery($query, $params);
		if ($result[0])
			return $result[1]->rowCount();
		else return false;
	}

	public function createTable(string $tableName, array $columns): bool
	{
		/**
		 * Create a table in the database.
		 *
		 * @param string $tableName  Name of the table to be created.
		 * @param array  $columns    An associative array where keys are column names and values are column definitions.
		 * @return bool  True if the table is created successfully, false otherwise.
		 */
		$query = "CREATE TABLE IF NOT EXISTS $tableName (" . implode(', ', $columns) . ")";

		try {
			$this->db->exec($query);
			return true;
		} catch (PDOException $exception) {
			echo $exception->getMessage();
			return false;
		}
	}

	public function dropTable(string $tableName): bool
	{
		/**
		 * Drop a table from the database.
		 * @param string $tableName  Name of the table to be dropped.
		 * @return bool  True if the table is dropped successfully, false otherwise.
		 */
		$query = "DROP TABLE $tableName";

		try {
			$this->db->exec($query);
			return true;
		} catch (PDOException $exception) {
			echo $exception->getMessage();
			return false;
		}
	}

	public function tableExists(string $table_name)
	{
		return (bool) $this->select('COUNT(*) AS count', 'information_schema.TABLES', [
			'`TABLE_SCHEMA`' => $_ENV['DB_NAME'],
			'`TABLE_NAME`' => $table_name
		])[0]['count'];
	}

	public function columnExists(string $table_name, string $column_name)
	{
		return (bool) $this->select('COUNT(*) AS count', 'information_schema.COLUMNS', [
			'`TABLE_SCHEMA`' => $_ENV['DB_NAME'],
			'`TABLE_NAME`' => $table_name,
			'`COLUMN_NAME`' => $column_name
		])[0]['count'];
	}

	public function constraintExists(string $table_name, string $constraint_name): bool
	{
		/**
		 * @throws TableNotFound
		 */
		if (!$this->tableExists($table_name)) {
			throw new TableNotFound("Table '$table_name' does not exist.");
		}

		return (bool) $this->select('COUNT(*) AS count', 'information_schema.TABLE_CONSTRAINTS', [
			'`TABLE_SCHEMA`' => $_ENV['DB_NAME'],
			'`TABLE_NAME`' => $table_name,
			'`CONSTRAINT_NAME`' => $constraint_name
		])[0]['count'];
	}

	public function addColumn(string $table_name, string $column_name, string $def): bool
	{
		/**
		 * @throws TableNotFound
		 * @throws ColumnAlreadyExists
		 */
		if (!$this->tableExists($table_name)) {
			throw new TableNotFound("Table '$table_name' does not exist.");
		}

		if ($this->columnExists($table_name, $column_name)) {
			throw new ColumnAlreadyExists("Column '$column_name' already exists in table '$table_name'.");
		}

		// Add the column
		$alterTableSQL = "ALTER TABLE $table_name ADD $column_name $def";
		$this->db->exec($alterTableSQL);
		return true;
	}

	public function modifyColumn(string $table_name, string $column_name, string $def): bool
	{
		/**
		 * Modify a column in the specified table.
		 * 
		 * @param string $table_name   The name of the table containing the column to be modified.
		 * @param string $column_name  The name of the column to be modified.
		 * @param string $def          The new definition for the column (e.g., data type and constraints).
		 * @return bool                True if the column is modified successfully, false otherwise.
		 * 
		 * @throws TableNotFound       If the specified table does not exist.
		 * @throws ColumnNotFound      If the specified column does not exist.
		 */

		if (!$this->tableExists($table_name)) {
			throw new TableNotFound("Table '$table_name' does not exist.");
		}

		if (!$this->columnExists($table_name, $column_name)) {
			throw new ColumnNotFound("Column '$column_name' does not exist in table '$table_name'.");
		}

		// Modify the column
		$alterTableSQL = "ALTER TABLE $table_name MODIFY $column_name $def";
		try {
			$this->db->exec($alterTableSQL);
			return true;
		} catch (PDOException $exception) {
			echo $exception->getMessage();
			return false;
		}
	}

	public function addFK(string $table_name, string $constraint_name, string $column_name, string $def): bool
	{
		/**
		 * @throws TableNotFound
		 * @throws ColumnAlreadyExists
		 */
		if (!$this->tableExists($table_name)) {
			throw new TableNotFound("Table '$table_name' does not exist.");
		}

		if ($this->columnExists($table_name, $column_name)) {
			throw new ColumnAlreadyExists("Column '$column_name' already exists in table '$table_name'.");
		}

		if ($this->constraintExists($table_name, $constraint_name))
			throw new ConstraintAlreadyExists("Constraint $constraint_name Already Exists in table $table_name");

		// Add the column
		$alterTableSQL = "ALTER TABLE $table_name ADD CONSTRAINT $constraint_name FOREIGN KEY ($column_name) $def";
		$this->db->exec($alterTableSQL);
		return true;
	}
}

class ColumnAlreadyExists extends Exception
{
}
class ConstraintAlreadyExists extends Exception
{
}
class TableNotFound extends Exception
{
}
class ColumnNotFound extends Exception
{
}