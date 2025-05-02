<?php

class InvalidArguments extends Exception
{
  public function __construct(string $msg)
  {
    parent::__construct($msg, 500);
  }
}