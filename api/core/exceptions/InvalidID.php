<?php

class InvalidID extends RuntimeException
{
  public function __construct($class, $id)
  {
    parent::__construct("$id is not a valid id for $class", 500);
  }
}