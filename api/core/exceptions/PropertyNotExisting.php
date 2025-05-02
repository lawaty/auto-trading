<?php

class PropertyNotExisting extends RuntimeException
{
  public function __construct($property, $class)
  {
    parent::__construct("$property does not exist for object of type $class", 500);
  }
}