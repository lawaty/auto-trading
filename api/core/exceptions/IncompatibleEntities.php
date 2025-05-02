<?php

class IncompatibleEntities extends RuntimeException
{
  public function __construct($original, $to_be_copied)
  {
    parent::__construct("Cannot copy properties from object of type $to_be_copied into an object of type $original", 500);
  }
}