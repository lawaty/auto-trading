<?php

class RequiredPropertyNotFound extends RuntimeException
{
  public function __construct($property, $class)
  {
    parent::__construct("$property is required for object of type $class", 500);
  }
}