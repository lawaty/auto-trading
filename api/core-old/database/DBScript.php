<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$creations = require APP_DIR . "/model/database/creations.php";
$insertions = require APP_DIR . "/model/database/insertions.php";

$db = DB::getDB();

// Dropping
if (isset($_GET['drop'])) {
  echo "<br>Dropping Tables...<br>";
  if ($_GET['drop'] == 'all')
    $tables = array_keys($creations);
  else
    $tables = explode(',', $_GET['drop']);

  $successes = 0;
  $failures = 0;
  foreach ($tables as $table) {
    if ($db->dropTable($table))
      $successes++;
    else
      $failures++;
  }
  echo "<br>$successes Successes and $failures Failures<br>";
}

// Creating
echo "<br>Creating Tables...<br>";

$successes = 0;
$failures = 0;
foreach ($creations as $tablename => $columns) {
  if ($db->createTable($tablename, $columns))
    $successes++;
  else
    $failures++;
}

// Updating table join map
$refs = DB::getDB()->select('TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME', 'INFORMATION_SCHEMA.KEY_COLUMN_USAGE', ['TABLE_SCHEMA' => $_ENV['DB_NAME'], ' AND REFERENCED_TABLE_NAME IS NOT NULL']);

$joins = [];
foreach ($refs as $ref) {
  $table1 = $ref['TABLE_NAME'];
  $column1 = $ref['COLUMN_NAME'];
  $table2 = $ref['REFERENCED_TABLE_NAME'];
  $column2 = $ref['REFERENCED_COLUMN_NAME'];

  if (!isset($joins[$table1]))
    $joins[$table1] = [];
  if (!isset($joins[$table2]))
    $joins[$table2] = [];

  $joins[$table1][$table2] = "$table1.$column1 = $table2.$column2";
  $joins[$table2][$table1] = "$table1.$column1 = $table2.$column2";
}

file_put_contents(APP_DIR . "/database/joins.json", json_encode($joins));

echo "<br>$successes Successes and $failures Failures<br>";

// Inserting
echo "<br>Checking and Applying Insertions... <br>";

$successes = 0;
$failures = 0;
foreach ($insertions as $table => $table_insertions) {
  foreach ($table_insertions as $insertion) {
    if (count($db->select('*', $table, $insertion))) {
      $successes++;
      continue;
    }

    if ($db->insert($table, $insertion))
      $successes++;
    else
      $failures++;
  }
}

echo "$successes Successes and $failures Failures<br>";
