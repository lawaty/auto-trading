<?php

class JoinImpossible extends RuntimeException
{
  public function __construct($table1, $referencing_tables)
  {
    parent::__construct("$table1 is neither referenced nor referencing any of these tables " . json_encode($referencing_tables), 500);
  }
}